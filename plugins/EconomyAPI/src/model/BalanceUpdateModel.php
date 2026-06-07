<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\model;

use alvin0319\StarGatePolyfill\AutoSerializedModel;

final class BalanceUpdateModel extends AutoSerializedModel{
	public function __construct(
		public string $xuid,
		public int $currencyId
	){}
}