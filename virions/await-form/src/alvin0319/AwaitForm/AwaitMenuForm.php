<?php

declare(strict_types=1);

namespace alvin0319\AwaitForm;

use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

final class AwaitMenuForm extends MenuForm{

	/** @phpstan-var \Closure(int|null) : void */
	private ?\Closure $resolver = null;

	/** @param MenuOption[] $options */
	public function __construct(string $title, string $text, array $options){
		parent::__construct(
			$title,
			$text,
			$options,
			function(Player $player, int $selectedOption) : void{
				if($this->resolver !== null){
					($this->resolver)($selectedOption);
					$this->resolver = null;
				}
			},
			function(Player $player) : void{
				if($this->resolver !== null){
					($this->resolver)(null);
					$this->resolver = null;
				}
			}
		);
	}

	/**
	 * @phpstan-return \Generator<mixed, mixed, mixed, int|null>
	 */
	public function send(Player $player) : \Generator{
		return yield from Await::promise(function($resolve) use ($player){
			$this->resolver = $resolve;
			$player->sendForm($this);
		});
	}
}