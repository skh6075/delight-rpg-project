<?php

declare(strict_types=1);

namespace alvin0319\AwaitCommand;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use SOFe\AwaitGenerator\Await;

abstract class AwaitCommand extends Command{

	final public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		Await::f2c(function() use ($sender, $commandLabel, $args) : \Generator{
			yield from $this->executeAsync($sender, $commandLabel, $args);
		});
	}

	/**
	 * @param string[]             $args
	 *
	 * @phpstan-param list<string> $args
	 * @phpstan-return \Generator<mixed, mixed, mixed, void>
	 */
	abstract public function executeAsync(CommandSender $sender, string $commandLabel, array $args) : \Generator;
}
