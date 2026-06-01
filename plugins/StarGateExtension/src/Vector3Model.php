<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use pocketmine\math\Vector3;

final class Vector3Model implements \JsonSerializable{

	public float $x;
	public float $y;
	public float $z;

	public function __construct(Vector3|float $x, float $y = 0.0, float $z = 0.0){
		if($x instanceof Vector3){
			$this->x = $x->x;
			$this->y = $x->y;
			$this->z = $x->z;
		}else{
			$this->x = $x;
			$this->y = $y;
			$this->z = $z;
		}
	}

	/** @phpstan-return array<string, int|float> */
	public function jsonSerialize() : array{
		return [
			"x" => $this->x,
			"y" => $this->y,
			"z" => $this->z,
		];
	}
}
