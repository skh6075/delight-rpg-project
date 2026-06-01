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

use InvalidArgumentException;
use pocketmine\form\FormValidationException;

class Dropdown extends CustomFormElement{
	public function __construct(
		string $name,
		string $text,
		protected array $options = [],
		protected ?string $tooltip = null,
		protected int $defaultOptionIndex = 0
	){
		parent::__construct($name, $text);
		$this->options = array_values($this->options);
		if(!isset($this->options[$defaultOptionIndex])){
			throw new InvalidArgumentException("No option at index $defaultOptionIndex, cannot set as default");
		}
	}

	public function validateValue($value) : void{
		if(!is_int($value)){
			throw new FormValidationException("Expected int, got " . gettype($value));
		}
		if(!isset($this->options[$value])){
			throw new FormValidationException("Option $value does not exist");
		}
	}

	public function getOption(int $index) : ?string{
		return $this->options[$index] ?? null;
	}

	public function getTooltip(): ?string{
		return $this->tooltip;
	}

	public function getDefaultOptionIndex() : int{
		return $this->defaultOptionIndex;
	}

	public function getDefaultOption() : string{
		return $this->options[$this->defaultOptionIndex];
	}

	/**
	 * @return string[]
	 */
	public function getOptions() : array{
		return $this->options;
	}

	public function getType() : string{
		return "dropdown";
	}

	protected function serializeElementData() : array{
		$json = [
			"options" => $this->options,
			"default" => $this->defaultOptionIndex
		];
		if($this->tooltip !== null){
			$json["tooltip"] = $this->tooltip;
		}

		return $json;
	}
}
