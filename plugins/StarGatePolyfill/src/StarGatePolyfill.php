<?php

declare(strict_types=1);

namespace alvin0319\StarGatePolyfill;

use alvin0319\StarGateExtension\StarGateExtension;
use pocketmine\plugin\PluginBase;

final class StarGatePolyfill extends PluginBase{

	private static bool $starGateFound = false;

	private static string $serverName = "";

	protected function onEnable() : void{
		self::$starGateFound = $this->getServer()->getPluginManager()->getPlugin("StarGate-Atlantis") !== null &&
			$this->getServer()->getPluginManager()->getPlugin("StarGateExtension") !== null;
		if(self::$starGateFound){
			self::$serverName = StarGateExtension::getServerName();
		}
	}

	public static function registerUpdateNotify(string $identifier, string $class, \Closure $handler) : void{
		if(!self::$starGateFound){
			return;
		}
		StarGateExtension::registerUpdateNotify($identifier, $class, $handler);
	}

	public static function notifyUpdate(string $identifier, \JsonSerializable $object) : void{
		if(!self::$starGateFound){
			return;
		}
		StarGateExtension::notifyUpdate($identifier, $object);
	}

	public static function getServerName() : string{
		return self::$serverName;
	}
}
