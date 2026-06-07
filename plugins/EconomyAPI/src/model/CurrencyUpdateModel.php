<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\model;

use alvin0319\EconomyAPI\data\Currency;
use alvin0319\StarGatePolyfill\AutoSerializedModel;

final class CurrencyUpdateModel extends AutoSerializedModel{
	public const int TYPE_REGISTER = 0;
	public const int TYPE_UNREGISTER = 1;
	public const int TYPE_UPDATE = 2;

	public function __construct(
		public int $type,
		public CurrencyModel $currencyModel
	){}

	public static function register(Currency $currency): self{
		return new self(self::TYPE_REGISTER, $currency->toModel());
	}

	public static function unregister(Currency $currency): self{
		return new self(self::TYPE_UNREGISTER, $currency->toModel());
	}

	public static function update(Currency $currency): self{
		return new self(self::TYPE_UPDATE, $currency->toModel());
	}
}