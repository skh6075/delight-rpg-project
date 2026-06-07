<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\model;

use alvin0319\StarGatePolyfill\AutoSerializedModel;

final class CurrencyModel extends AutoSerializedModel{
	public function __construct(
		public int $id,
		public string $name,
		public string $symbol,
		public int $defaultBalance,
		public bool $canTransaction,
		public bool $isDefault
	){}
}