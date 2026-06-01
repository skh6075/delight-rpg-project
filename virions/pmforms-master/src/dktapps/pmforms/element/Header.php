<?php

declare(strict_types=1);

namespace dktapps\pmforms\element;

class Header extends Label{
	public static function create(string $text): Header{
		return new self("", $text);
	}

	public function getType() : string{
		return "header";
	}

	public function validateValue($value) : void{
		assert($value === null);
	}

	protected function serializeElementData() : array{
		return [];
	}
}