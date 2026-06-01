<?php

declare(strict_types=1);

namespace alvin0319\AwaitForm;

use dktapps\pmforms\ModalForm;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class AwaitForm{

	private static ?PluginBase $plugin = null;

	public static function init(PluginBase $plugin) : void{
		if(self::$plugin === null){
			self::$plugin = $plugin;
			$plugin->getServer()->getPluginManager()->registerEvent(PlayerQuitEvent::class, self::onPlayerQuit(...), EventPriority::NORMAL, $plugin);
		}
	}

	private static function onPlayerQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();
		\Closure::bind(function() : void{
			foreach($this->forms as $id => $form){
				$this->onFormSubmit($id, $form instanceof ModalForm ? false : null);
			}
		}, $player, Player::class)->call($player);
	}
}
