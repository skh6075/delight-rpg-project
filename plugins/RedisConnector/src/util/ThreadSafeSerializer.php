<?php

declare(strict_types=1);

namespace alvin0319\RedisConnector\util;

use pmmp\thread\ThreadSafeArray;
use Predis\Response\Status;

final class ThreadSafeSerializer{

	/**
	 * @param array<mixed> $value
	 */
	public static function encodeArray(array $value) : ThreadSafeArray{
		/** @var ThreadSafeArray $encoded */
		$encoded = self::encode($value);
		return $encoded;
	}

	/**
	 * @return array<mixed>
	 */
	public static function decodeArray(ThreadSafeArray $value) : array{
		/** @var array<mixed> $decoded */
		$decoded = self::decode($value);
		return $decoded;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public static function encode(mixed $value) : mixed{
		if($value instanceof Status){
			return $value->getPayload();
		}

		if(is_array($value)){
			$encoded = [];
			foreach($value as $key => $item){
				$encoded[$key] = self::encode($item);
			}

			return ThreadSafeArray::fromArray($encoded);
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public static function decode(mixed $value) : mixed{
		if($value instanceof ThreadSafeArray){
			$decoded = [];
			foreach($value as $key => $item){
				$decoded[$key] = self::decode($item);
			}

			return $decoded;
		}

		return $value;
	}
}
