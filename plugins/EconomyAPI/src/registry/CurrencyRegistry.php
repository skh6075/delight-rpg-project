<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\registry;

use alvin0319\EconomyAPI\data\Currency;
use alvin0319\EconomyAPI\EconomyAPI;
use alvin0319\EconomyAPI\model\CurrencyUpdateModel;
use alvin0319\EconomyAPI\service\EconomyService;

final class CurrencyRegistry{
	/**
	 * @var Currency[]
	 * @phpstan-var array<int, Currency>
	 */
	private array $currencies = [];
	/**
	 * @var Currency[]
	 * @phpstan-var array<string, Currency>
	 */
	private array $nameToCurrency = [];

	private Currency $defaultCurrency;

	public function setup(EconomyService $service): \Generator{
		if(!isset($this->defaultCurrency) && $this->currencies === []){
			$this->defaultCurrency = yield from $service->createCurrency("골드", "§l§6G§r", 1000, true, true);
			$this->register($this->defaultCurrency);
		}
	}

	public function register(Currency $currency): void{
		$this->currencies[$currency->id] = $currency;
		$this->nameToCurrency[$currency->name] = $currency;
		if($currency->isDefault){
			$this->onChangeDefaultCurrency($currency);
		}
	}

	public function unregister(Currency $currency): void{
		unset($this->currencies[$currency->id], $this->nameToCurrency[$currency->name]);
	}

	public function getDefaultCurrency(): Currency{
		return $this->defaultCurrency;
	}

	public function getCurrencyOrDefault(string $name): Currency{
		return $this->nameToCurrency[$name] ?? $this->getDefaultCurrency();
	}

	public function getCurrencyOrNull(string $name): ?Currency{
		return $this->nameToCurrency[$name] ?? null;
	}

	public function find(int $id): ?Currency{
		return $this->currencies[$id] ?? null;
	}

	public function onChangeDefaultCurrency(Currency $currency): void{
		if(!$currency->isDefault){
			return;
		}
		if(isset($this->defaultCurrency)){
			$this->defaultCurrency->isDefault = false;
		}
		$this->defaultCurrency = $currency;
	}

	/** @phpstan-return array<int, Currency> */
	public function getAll(): array{
		return array_values($this->currencies);
	}

	public function onUpdate(CurrencyUpdateModel $model): void{
		$currency = Currency::fromModel($model->currencyModel);
		switch($model->type){
			case CurrencyUpdateModel::TYPE_REGISTER:
				$this->register($currency);
				break;
			case CurrencyUpdateModel::TYPE_UNREGISTER:
				$this->unregister($currency);
				break;
			case CurrencyUpdateModel::TYPE_UPDATE:
				$this->find($currency->id)?->onUpdate($currency);
				$this->onChangeDefaultCurrency($currency);
				break;
		}
	}
}