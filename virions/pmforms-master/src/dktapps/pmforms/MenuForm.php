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

namespace dktapps\pmforms;

use Closure;
use dktapps\pmforms\element\Divider;
use dktapps\pmforms\element\Header;
use dktapps\pmforms\element\Label;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use function array_values;
use function gettype;
use function is_int;
use function var_dump;

/**
 * This form type presents a menu to the user with a list of options on it. The user may select an option or close the
 * form by clicking the X in the top left corner.
 *
 * @phpstan-type OnSubmit Closure(Player $player, int $selectedOption) : void
 * @phpstan-type OnClose Closure(Player $player) : void
 */
class MenuForm extends BaseForm{
	/**
	 * @param string       $title
	 * @param string       $text
	 * @param array<MenuOption|Label> $options
	 * @param Closure<Player, int> 	  $onSubmit
	 * @param Closure<Player>|null    $onClose
	 */
	public function __construct(
		string $title,
		protected string $text,
		protected array $options,
		protected Closure $onSubmit,
		protected ?Closure $onClose = null
	){
		parent::__construct($title);
		Utils::validateCallableSignature(function(Player $player, int $selectedOption): void{}, $onSubmit);
		if($onClose !== null){
			Utils::validateCallableSignature(function(Player $player): void{}, $onClose);
		}
	}

	public function getOption(int $position): ?MenuOption{
		return $this->options[$position] ?? null;
	}

	final public function handleResponse(Player $player, $data) : void{
		if($data === null){
			if($this->onClose !== null){
				($this->onClose)($player);
			}
		}elseif(is_int($data)){
			if(!isset($this->options[$data])){
				throw new FormValidationException("Option $data does not exist");
			}
			($this->onSubmit)($player, $data);
		}else{
			throw new FormValidationException("Expected int or null, got " . gettype($data));
		}
	}

	protected function getType() : string{
		return "form";
	}

	protected function serializeFormData() : array{
		return [
			"content" => $this->text,
			"elements" => $this->options //yes, this is intended (MCPE calls them buttons)
		];
	}
}
