<?php

/*
 * This file is part of pmforms.
 * Copyright (C) 2018-2025 Dylan K. Taylor <https://github.com/dktapps-pm-pl/pmforms>
 *
 * pmforms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace dktapps\pmforms\element;

use JsonSerializable;
use pocketmine\form\FormValidationException;

abstract class CustomFormElement implements JsonSerializable{
	public function __construct(
		private string $name,
		private string $text
	){}

	abstract public function getType(): string;

	public function getName(): string{
		return $this->name;
	}

	public function getText(): string{
		return $this->text;
	}

	abstract public function validateValue(mixed $value): void;

	final public function jsonSerialize(): array{
		$ret = $this->serializeElementData();
		$ret["type"] = $this->getType();
		$ret["text"] = $this->getText();

		return $ret;
	}

	abstract protected function serializeElementData() : array;
}
