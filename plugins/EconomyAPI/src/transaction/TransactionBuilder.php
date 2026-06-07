<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\transaction;

use alvin0319\EconomyAPI\data\Currency;
use alvin0319\EconomyAPI\session\EconomySession;
use pocketmine\player\Player;

final class TransactionBuilder{
	private EconomySession|Player|string $player;

	private TransactionType $type;

	private int $amount;

	private ?Currency $currency = null;

	private ?string $reason = null;

	private EconomySession|Player|string|null $target = null;

	public static function make() : self{
		return new self();
	}

	public function player(EconomySession|Player|string $player) : TransactionBuilder{
		$this->player = $player;
		return $this;
	}

	public function type(TransactionType $type) : TransactionBuilder{
		$this->type = $type;
		return $this;
	}

	public function amount(int $amount) : TransactionBuilder{
		if($amount < 0){
			throw new \InvalidArgumentException("Amount must be non-negative");
		}
		$this->amount = $amount;
		return $this;
	}

	public function currency(?Currency $currency) : TransactionBuilder{
		$this->currency = $currency;
		return $this;
	}

	public function reason(?string $reason) : TransactionBuilder{
		$this->reason = $reason;
		return $this;
	}

	public function target(EconomySession|Player|string|null $target) : TransactionBuilder{
		$this->target = $target;
		return $this;
	}

	public function build() : Transaction{
		if(!isset($this->player)){
			throw new \LogicException("Player is not set");
		}
		if(!isset($this->type)){
			throw new \LogicException("Transaction type is not set");
		}
		if(!isset($this->amount)){
			throw new \LogicException("Transaction amount is not set");
		}
		if($this->type === TransactionType::PAY && $this->target === null){
			throw new \LogicException("Target must be set for PAY transaction");
		}
		return new Transaction(
			$this->player,
			$this->type,
			$this->amount,
			$this->currency,
			$this->reason,
			$this->target
		);
	}
}