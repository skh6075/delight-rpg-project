<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\utils;

use alvin0319\EconomyAPI\data\Currency;

final class CurrencyFormatter{
	private const array KOREAN_BIG_ORDERS = ["", "만", "억", "조", "경"];

	/** @phpstan-param array<string> $bigOrders */
	public static function kindly(int $number, Currency $currency, ?array $bigOrders = null): string{
		if($number < 10000){
			return ((string) $number) . $currency->symbol;
		}
		$bigOrders ??= self::KOREAN_BIG_ORDERS;
		$str = "";
		for($i = count($bigOrders) - 1; $i >= 0; --$i){
			$unit = 10000 ** $i;
			$part = floor($number / $unit);
			if($part > 0){
				$str .= $part . $bigOrders[$i];
			}
			$number %= $unit;
		}
		return "§r§f" . $str . $currency->symbol;
	}

	/** @phpstan-param array<string> $bigOrders */
	public static function compact(int $number, Currency $currency, ?array $bigOrders = null): string{
		if($number < 10000){
			return ((string) $number) . $currency->symbol;
		}

		$units = $bigOrders ?? self::KOREAN_BIG_ORDERS;
		$unit_sizeof = count($units) - 1;
		$decimal = max(0, floor(log10($number) / $unit_sizeof));
		$number /= pow(10, $decimal * $unit_sizeof);

		return "§r§f" . floor($number * 10) / 10 . $units[(int) $decimal] . $currency->symbol;
	}
}