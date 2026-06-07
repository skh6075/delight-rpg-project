<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\session;

use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\model\BalanceUpdateModel;
use alvin0319\SessionManager\BaseSession;
use alvin0319\StarGatePolyfill\StarGatePolyfill;
use pocketmine\player\Player;
use alvin0319\EconomyAPI\data\Balance;
use alvin0319\EconomyAPI\data\Currency;
use SOFe\AwaitGenerator\Await;

final class EconomySession extends BaseSession{
	/**
	 * @param array<int, Balance> $balances
	 *
	 * @phpstan-param array<int, Balance> $balances
	 */
	public function __construct(
		string $xuid,
		?Player $player = null,
		private array $balances = []
	){
		parent::__construct($xuid, $player);
	}

	/** @phpstan-return array<int, Balance> */
	public function getBalances(): array{
		return $this->balances;
	}

	public function getBalance(?Currency $currency = null): Balance{
		$currency ??= EconomyAPI::getInstance()->getDefaultCurrency();
		if(!isset($this->balances[$currency->id])){ //서버 중간에 재화가 등록된 경우
			$this->balances[$currency->id] = new Balance($currency, $currency->defaultBalance);
			Await::g2c(EconomyAPI::getInstance()->getService()->saveBalance($this->xuid, $currency->id, $currency->defaultBalance));
		}
		return $this->balances[$currency->id];
	}

	public function getBalanceAmount(?Currency $currency): int{
		return $this->getBalance($currency)->getAmount();
	}

	public function updateBalance(int $amount, ?Currency $currency = null): void{
		$currency ??= EconomyAPI::getInstance()->getDefaultCurrency();
		$this->balances[$currency->id]->setAmount($amount);
		Await::g2c($this->save(false));
	}

	public function save(bool $offline) : \Generator{
		foreach($this->balances as $balance){
			yield from EconomyAPI::getInstance()->getService()->saveBalance($this->xuid, $balance->getCurrency()->id, $balance->getAmount());
			StarGatePolyfill::notifyUpdate(EconomyAPI::IDENTIFIER_BALANCE_UPDATE, new BalanceUpdateModel($this->xuid, $balance->getCurrency()->id));
		}
	}
}