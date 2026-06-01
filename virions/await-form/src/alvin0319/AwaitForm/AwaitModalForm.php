<?php

declare(strict_types=1);

namespace alvin0319\AwaitForm;

use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\ModalForm;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

final class AwaitModalForm extends ModalForm{

	/** @phpstan-var \Closure(bool) : void */
	private ?\Closure $resolver = null;

	public function __construct(string $title, string $text, string $yesButtonText = "gui.yes", string $noButtonText = "gui.no"){
		parent::__construct(
			$title,
			$text,
			function(Player $player, bool $choice) : void{
				($this->resolver)($choice);
			},
			$yesButtonText,
			$noButtonText
		);
	}

	/** @phpstan-return \Generator<mixed, mixed, mixed, CustomFormResponse|null> */
	public function send(Player $player) : \Generator{
		return yield from Await::promise(function($resolve) use ($player){
			$this->resolver = $resolve;
			$player->sendForm($this);
		});
	}
}
