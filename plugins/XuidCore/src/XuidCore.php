<?php

declare(strict_types=1);

namespace alvin0319\XuidCore;

use Generator;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use skh6075\ExtensionPlugin\ExtensionPlugin;
use SOFe\AwaitGenerator\Await;
use function count;
use function strtolower;
use function time;

final class XuidCore extends PluginBase implements Listener{
	use SingletonTrait;

	private static DataConnector $database;

	/** @var array<string, string> */
	private static array $xuidToName = [];

	/** @var array<string, string|null> */
	private static array $xuidToNickname = [];

	/** @var array<string, string> */
	private static array $lowerNameToXuid = [];

	/** @var array<string, string> */
	private static array $lowerNicknameToXuid = [];

	/** @var array<string, int> */
	private static array $cacheAccessTime = [];

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->saveDefaultConfig();

		self::$database = ExtensionPlugin::createConnector($this);

		self::$database->executeGeneric("xuidcore.init");
		self::$database->waitAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->cleanupCache();
		}), 20 * 60);
	}

	protected function onDisable() : void{
		if(isset(self::$database)){
			self::$database->close();
		}
	}

	public static function getDatabase() : DataConnector{
		return self::$database;
	}

	public function onPlayerLogin(PlayerPreLoginEvent $event) : void{
		$info = $event->getPlayerInfo();
		if(!$info instanceof XboxLivePlayerInfo){
			return;
		}
		$xuid = $info->getXuid();
		$name = $info->getUsername();

		if($xuid === ""){
			return;
		}

		Await::f2c(function() use ($xuid, $name) : Generator{
			try{
				yield from self::savePlayerXuid($xuid, $name);
			}catch(\Throwable $e){
				XuidCore::getInstance()->getLogger()->logException($e);
			}
		});
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();
		$xuid = $player->getXuid();

		if($xuid === ""){
			return;
		}

		self::invalidateCache($xuid);
	}

	private static function invalidateCache(string $xuid) : void{
		$playerName = self::$xuidToName[$xuid] ?? null;
		if($playerName !== null){
			unset(self::$lowerNameToXuid[strtolower($playerName)]);
		}

		$nickname = self::$xuidToNickname[$xuid] ?? null;
		if($nickname !== null){
			unset(self::$lowerNicknameToXuid[strtolower($nickname)]);
		}

		unset(
			self::$xuidToName[$xuid],
			self::$xuidToNickname[$xuid],
			self::$cacheAccessTime[$xuid]
		);
	}

	private function cleanupCache() : void{
		$currentTime = time();
		$toRemove = [];

		foreach(self::$cacheAccessTime as $xuid => $lastAccess){
			if($currentTime - $lastAccess > 120){
				$player = ExtensionPlugin::findPlayerByXuid((string) $xuid);
				if($player === null || !$player->isOnline()){
					$toRemove[] = $xuid;
				}
			}
		}

		foreach($toRemove as $xuid){
			self::invalidateCache($xuid);
		}
	}

	/** @phpstan-return Generator<mixed, mixed, mixed, void> */
	public static function savePlayerXuid(string $xuid, string $playerName) : Generator{
		try{
			$lowerName = strtolower($playerName);

			self::$xuidToName[$xuid] = $playerName;
			self::$lowerNameToXuid[$lowerName] = $xuid;
			self::$cacheAccessTime[$xuid] = time();

			if(!isset(self::$xuidToNickname[$xuid])){
				self::$xuidToNickname[$xuid] = null;
			}

			yield from self::$database->asyncInsert("xuidcore.save", [
				"xuid" => $xuid,
				"player_name" => $playerName,
				"player_name_lower" => $lowerName,
				"last_updated" => time()
			]);
		}catch(\Throwable){
		}
	}

	/** @phpstan-return Generator<mixed, mixed, mixed, string|null> */
	public static function lookupXuid(string $playerName) : Generator{
		try{
			$lowerName = strtolower($playerName);

			if(isset(self::$lowerNicknameToXuid[$lowerName])){
				$xuid = self::$lowerNicknameToXuid[$lowerName];
				self::$cacheAccessTime[$xuid] = time();
				return $xuid;
			}

			if(isset(self::$lowerNameToXuid[$lowerName])){
				$xuid = self::$lowerNameToXuid[$lowerName];
				self::$cacheAccessTime[$xuid] = time();
				return $xuid;
			}

			$rows = yield from self::$database->asyncSelect("xuidcore.lookup.xuid.nickname", [
				"nickname_lower" => $lowerName
			]);

			if(count($rows) > 0){
				$xuid = $rows[0]["xuid"];
				self::$lowerNicknameToXuid[$lowerName] = $xuid;
				self::$cacheAccessTime[$xuid] = time();
				return $xuid;
			}

			$rows = yield from self::$database->asyncSelect("xuidcore.lookup.xuid.name", [
				"player_name_lower" => $lowerName
			]);

			if(count($rows) === 0){
				return null;
			}

			$xuid = $rows[0]["xuid"];
			self::$lowerNameToXuid[$lowerName] = $xuid;
			self::$cacheAccessTime[$xuid] = time();
			return $xuid;
		}catch(\Throwable){
			return null;
		}
	}

	/** @phpstan-return Generator<mixed, mixed, mixed, string|null> */
	public static function lookupPlayerName(string $xuid) : Generator{
		try{
			if(isset(self::$xuidToName[$xuid])){
				self::$cacheAccessTime[$xuid] = time();
				return self::$xuidToName[$xuid];
			}

			$rows = yield from self::$database->asyncSelect("xuidcore.lookup.name", [
				"xuid" => $xuid
			]);

			if(count($rows) === 0){
				return null;
			}

			$playerName = $rows[0]["player_name"];
			self::$xuidToName[$xuid] = $playerName;
			self::$lowerNameToXuid[strtolower($playerName)] = $xuid;
			self::$cacheAccessTime[$xuid] = time();

			return $playerName;
		}catch(\Throwable){
			return null;
		}
	}

	/** @phpstan-return Generator<mixed, mixed, mixed, string|null> */
	public static function lookupNickname(string $xuid) : Generator{
		try{
			if(isset(self::$xuidToNickname[$xuid])){
				self::$cacheAccessTime[$xuid] = time();
				return self::$xuidToNickname[$xuid];
			}

			$rows = yield from self::$database->asyncSelect("xuidcore.lookup.nickname", [
				"xuid" => $xuid
			]);

			if(count($rows) === 0){
				self::$xuidToNickname[$xuid] = null;
				self::$cacheAccessTime[$xuid] = time();
				return null;
			}

			$nickname = $rows[0]["nickname"];
			self::$xuidToNickname[$xuid] = $nickname;
			self::$cacheAccessTime[$xuid] = time();
			if($nickname !== null){
				self::$lowerNicknameToXuid[strtolower($nickname)] = $xuid;
			}

			return $nickname;
		}catch(\Throwable){
			return null;
		}
	}

	/** @phpstan-return Generator<mixed, mixed, mixed, void> */
	public static function setNickname(string $xuid, string $nickname) : Generator{
		try{
			$lowerNickname = strtolower($nickname);

			$oldNickname = self::$xuidToNickname[$xuid] ?? null;
			if($oldNickname !== null){
				unset(self::$lowerNicknameToXuid[strtolower($oldNickname)]);
			}

			self::$xuidToNickname[$xuid] = $nickname;
			self::$lowerNicknameToXuid[$lowerNickname] = $xuid;

			yield from self::$database->asyncGeneric("xuidcore.nickname.set", [
				"xuid" => $xuid,
				"nickname" => $nickname,
				"nickname_lower" => $lowerNickname
			]);
		}catch(\Throwable){
		}
	}

	/** @phpstan-return Generator<mixed, mixed, mixed, void> */
	public static function removeNickname(string $xuid) : Generator{
		try{
			$oldNickname = self::$xuidToNickname[$xuid] ?? null;
			if($oldNickname !== null){
				unset(self::$lowerNicknameToXuid[strtolower($oldNickname)]);
			}

			self::$xuidToNickname[$xuid] = null;

			yield from self::$database->asyncGeneric("xuidcore.nickname.remove", [
				"xuid" => $xuid
			]);
		}catch(\Throwable){
		}
	}
}
