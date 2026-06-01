<?php

declare(strict_types=1);

namespace alvin0319\AwaitForm;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\CustomFormElement;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

final class AwaitCustomForm extends CustomForm{

	/** @phpstan-var \Closure(CustomFormResponse|null) : void */
	private ?\Closure $resolver = null;

	/** @param CustomFormElement[] $elements */
	public function __construct(string $title, array $elements){
		parent::__construct(
			$title,
			$elements,
			function(Player $player, CustomFormResponse $response) : void{
				if($this->resolver !== null){
					($this->resolver)($response);
				}
			}, function(Player $player) : void{
			($this->resolver)(null);
		});
	}

	/** @phpstan-return \Generator<mixed, mixed, mixed, CustomFormResponse|null> */
	public function send(Player $player) : \Generator{
		return yield from Await::promise(function($resolve) use ($player){
			$this->resolver = $resolve;
			$player->sendForm($this);
		});
	}
}
