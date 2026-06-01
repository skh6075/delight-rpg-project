<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

use pocketmine\player\Player;

abstract class BaseSession{

	public const int MAX_OFFLINE_SECONDS = 120;

	private int $offlineSeconds = 0;

	public function __construct(
		protected string $xuid,
		protected ?Player $player = null
	){
	}

	final public function getXuid() : string{ return $this->xuid; }

	final public function getPlayer() : ?Player{ return $this->player; }

	final public function isOnline() : bool{ return $this->player !== null && $this->player->isConnected(); }

	abstract public function save(bool $offline) : \Generator;

	final public function internalTick() : void{
		if($this->player === null || !$this->player->isConnected()){
			$this->offlineSeconds++;
		}
		$this->tick();
	}

	final public function getOfflineSeconds() : int{
		return $this->offlineSeconds;
	}

	final public function switchOnline(Player $player) : void{
		$this->player = $player;
		$this->offlineSeconds = 0;
		$this->onPlayerOnline($player);
	}

	public function onPlayerOnline(Player $player) : void{
	}

	public function tick() : void{
	}
}
