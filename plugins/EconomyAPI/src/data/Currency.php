<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\data;

use alvin0319\EconomyAPI\model\CurrencyModel;

final class Currency{
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public string $symbol,
		public int $defaultBalance,
		public bool $canTransaction,
		public bool $isDefault
	){}

	public function equals(Currency $other): bool{
		return $this->id === $other->id && $this->name === $other->name;
	}

	public function toModel(): CurrencyModel{
		return new CurrencyModel($this->id, $this->name, $this->symbol, $this->defaultBalance, $this->canTransaction, $this->isDefault);
	}

	public function onUpdate(Currency $other): void{
		$this->symbol = $other->symbol;
		$this->defaultBalance = $other->defaultBalance;
		$this->canTransaction = $other->canTransaction;
		$this->isDefault = $other->isDefault;
	}

	public static function fromModel(CurrencyModel $model): self{
		return new self($model->id, $model->name, $model->symbol, $model->defaultBalance, $model->canTransaction, $model->isDefault);
	}
}