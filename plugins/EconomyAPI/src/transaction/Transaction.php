<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\transaction;

use alvin0319\EconomyAPI\data\Currency;
use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\event\EconomyTransactionEvent;
use alvin0319\EconomyAPI\session\EconomySession;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use const PHP_INT_MAX;

final readonly class Transaction{
	public function __construct(
		public EconomySession|Player|string $player,
		public TransactionType $type,
		public int $amount,
		public ?Currency $currency = null,
		public ?string $reason = null,
		public EconomySession|Player|string|null $target = null
	){}

	/** @phpstan-return \Generator<mixed, mixed, mixed, TransactionResult> */
	public function execute() : \Generator{
		try{
			$playerToString = function(EconomySession|Player|string $player) : string{
				if($player instanceof EconomySession){
					return $player->getXuid();
				}
				if($player instanceof Player){
					return $player->getXuid();
				}
				return $player;
			};
			/** @var EconomySession $session */
			$session = match (true) {
				$this->player instanceof EconomySession => $this->player,
				default => (yield from EconomyAPI::getInstance()->createSession($playerToString($this->player), $this->player instanceof Player ? $this->player : null))?->getSession() ?? throw new AssumptionFailedError("Failed to get EconomySession for player"),
			};
			$ev = new EconomyTransactionEvent($session, $this);
			$ev->call();
			if($ev->isCancelled()){
				return TransactionResult::CANCELLED;
			}
			switch($this->type){
				case TransactionType::ADD:
					$val = $session->getBalanceAmount($this->currency) + $this->amount;
					if($val - 1 >= PHP_INT_MAX){
						return TransactionResult::OVERFLOW;
					}
					$session->updateBalance($val, $this->currency);
					return TransactionResult::SUCCESS;
				case TransactionType::DEDUCT:
					$val = $session->getBalanceAmount($this->currency) - $this->amount;
					if($val < 0){
						return TransactionResult::INSUFFICIENT_FUNDS;
					}
					$session->updateBalance($val, $this->currency);
					return TransactionResult::SUCCESS;
				case TransactionType::PAY:
					if($this->target === null){
						throw new AssumptionFailedError("Target must be set for PAY transaction");
					}
					if($this->currency === null || !$this->currency->canTransaction){
						return TransactionResult::TRANSACTION_FAILED;
					}
					$targetSession = match (true) {
						$this->target instanceof EconomySession => $this->target,
						default => (yield from EconomyAPI::getInstance()->createSession($playerToString($this->target), $this->target instanceof Player ? $this->target : null))?->getSession() ?? throw new AssumptionFailedError("Failed to get target EconomySession for player"),
					};
					$senderVal = $session->getBalanceAmount($this->currency) - $this->amount;
					if($senderVal < 0){
						return TransactionResult::INSUFFICIENT_FUNDS;
					}
					$receiverVal = $targetSession->getBalanceAmount($this->currency) + $this->amount;
					if($receiverVal - 1 >= PHP_INT_MAX){
						return TransactionResult::OVERFLOW;
					}
					$session->updateBalance($senderVal, $this->currency);
					$targetSession->updateBalance($receiverVal, $this->currency);
					return TransactionResult::SUCCESS;
				case TransactionType::SET:
					$session->updateBalance($this->amount, $this->currency);
					return TransactionResult::SUCCESS;
			}
		}catch(\Throwable){
			return TransactionResult::UNKNOWN_ERROR;
		}
	}

	public static function builder() : TransactionBuilder{
		return new TransactionBuilder();
	}
}