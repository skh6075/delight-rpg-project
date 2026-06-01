<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

use pocketmine\player\Player;

/**
 * @template T of BaseSession
 */
trait SessionProviderTrait{

	/** @phpstan-var array<string, T> */
	protected array $sessions = [];

	/** @phpstan-return T */
	public function getSession(string|Player $playerOrXuid) : ?BaseSession{
		$xuid = $playerOrXuid instanceof Player ? $playerOrXuid->getXuid() : $playerOrXuid;
		return $this->sessions[$xuid] ?? null;
	}

	public function removeSession(string $xuid) : \Generator{
		$session = $this->sessions[$xuid] ?? null;
		if($session !== null){
			unset($this->sessions[$xuid]);
			try{
				yield from $session->save(true);
			}catch(\Throwable $e){
				$this->getLogger()->logException($e);
			}
		}
	}
}
