<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\service;

use Generator;
use poggit\libasynql\DataConnector;
use alvin0319\EconomyAPI\data\Currency;

final readonly class EconomyService{
	public function __construct(private DataConnector $connector){}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, void>
	 */
	public function init(): Generator{
		yield from $this->connector->asyncGeneric("init");
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, array<int, Currency>>
	 */
	public function loadCurrencies(): Generator{
		$currencies = [];
		$currencyData = yield from $this->connector->asyncSelect("load_currencies");

		if(count($currencyData) > 0){
			foreach($currencyData as $currencyRow){
				$currency = new Currency(
					id: $currencyRow["id"],
					name: $currencyRow["name"],
					symbol: $currencyRow["symbol"],
					defaultBalance: $currencyRow["default_balance"],
					canTransaction: $currencyRow["can_transaction"] === 1,
					isDefault: $currencyRow["is_default"] === 1
				);
				$currencies[$currency->id] = $currency;
			}
		}
		return $currencies;
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, array<int, int>>
	 */
	public function loadBalances(string $xuid): Generator{
		$balances = [];
		$balanceData = yield from $this->connector->asyncSelect("load_balances", [
			"xuid" => $xuid
		]);
		if(count($balanceData) > 0){
			foreach($balanceData as $balanceRow){
				$balances[$balanceRow["currency_id"]] = $balanceRow["balance"];
			}
		}
		return $balances;
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, int|null>
	 */
	public function getBalance(string $xuid, Currency $currency): Generator{
		$rows = yield from $this->connector->asyncSelect("load_balance", [
			"xuid" => $xuid,
			"currency_id" => $currency->id
		]);

		return count($rows) > 0 ? (int) $rows[0]["balance"] : null;
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, Currency>
	 */
	public function createCurrency(string $name, string $symbol, int $defaultBalance, bool $canTransaction, bool $isDefault): Generator{
		$rows = yield from $this->connector->asyncInsert("create_currency", [
			"name" => $name,
			"symbol" => $symbol,
			"default_balance" => $defaultBalance,
			"can_transaction" => $canTransaction ? 1 : 0,
			"is_default" => $isDefault ? 1 : 0
		]);
		$id = (int) $rows[0];

		return new Currency($id, $name, $symbol, $defaultBalance, $canTransaction, $isDefault);
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, void>
	 */
	public function deleteCurrency(Currency $currency): Generator{
		yield from $this->connector->asyncChange("delete_currency", [
			"id" => $currency->id
		]);
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, void>
	 */
	public function updateCurrency(Currency $currency): Generator{
		yield from $this->connector->asyncChange("update_currency", [
			"id" => $currency->id,
			"symbol" => $currency->symbol,
			"default_balance" => $currency->defaultBalance,
			"can_transaction" => $currency->canTransaction ? 1 : 0,
			"is_default" => $currency->isDefault ? 1 : 0
		]);
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, void>
	 */
	public function saveBalance(string $xuid, int $currencyId, int $balance): Generator{
		yield from $this->connector->asyncGeneric("save_balance", [
			"xuid" => $xuid,
			"currency_id" => $currencyId,
			"balance" => $balance
		]);
	}

	/**
	 * @safe-generator
	 *
	 * @phpstan-return Generator<mixed, mixed, mixed, list<array{xuid: string, balance: int}>>
	 */
	public function getTopBalances(int $currencyId, int $page, int $limit): Generator{
		$offset = ($page - 1) * $limit;
		return yield from $this->connector->asyncSelect("top_balance", [
			"currency_id" => $currencyId,
			"limit" =>  $limit,
			"offset" => $offset
		]);
	}
}