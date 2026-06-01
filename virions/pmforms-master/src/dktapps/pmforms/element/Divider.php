<?php

declare(strict_types=1);

namespace dktapps\pmforms\element;

class Divider extends Label{
	public static function create(): Divider{
		return new self("", "");
	}

	public function getType(): string{
		return "divider";
	}

	public function validateValue($value) : void{
		assert($value === null);
	}

	protected function serializeElementData() : array{
		return [];
	}
}