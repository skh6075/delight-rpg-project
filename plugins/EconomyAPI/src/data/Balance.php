<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\data;

final class Balance{
	public function __construct(
		private readonly Currency $currency,
		private int $amount
	){}

	public function getCurrency(): Currency{
		return $this->currency;
	}

	public function getAmount(): int{
		return $this->amount;
	}

	public function setAmount(int $amount): void{
		$this->amount = $amount;
	}
}