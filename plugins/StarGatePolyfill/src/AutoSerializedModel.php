<?php

declare(strict_types=1);

namespace alvin0319\StarGatePolyfill;

abstract class AutoSerializedModel implements \JsonSerializable{

	/** @phpstan-return array<string, mixed> */
	public function jsonSerialize() : array{
		$ref = new \ReflectionClass(static::class);
		$return = [];
		foreach($ref->getProperties() as $property){
			if($property->isPublic() && !$property->isStatic()){
				$return[$property->getName()] = $this->{$property->getName()};
			}
		}
		return $return;
	}
}
