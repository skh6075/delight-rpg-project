<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use alemiz\sga\events\ClientAuthenticatedEvent;
use alemiz\sga\protocol\StarGatePacket;
use alemiz\sga\StarGateAtlantis;
use pocketmine\event\EventPriority;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use function array_any;
use function class_exists;
use function count;
use function json_encode;
use const JSON_INVALID_UTF8_SUBSTITUTE;

final class StarGateExtension extends PluginBase{

	/** @phpstan-var array<string, list<UpdateNotifyEntry>> */
	private static array $updateNotifies = [];

	/** @phpstan-var list<ServerEntry> */
	public static array $onlineServers = [];

	/** @phpstan-var array<string, ServerStat> */
	public static array $serverStats = [];

	private static string $serverName = "";

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvent(ClientAuthenticatedEvent::class, function(ClientAuthenticatedEvent $event) : void{
			$codec = $event->getClient()->getProtocolCodec();
			$codec->registerPacket(UpdateForwardPacket::PACKET_ID, new UpdateForwardPacket());
			$codec->registerPacket(OnlineServerListPacket::PACKET_ID, new OnlineServerListPacket());
			$codec->registerPacket(ServerStatPacket::PACKET_ID, new ServerStatPacket());
			$codec->registerPacket(MessagePacket::PACKET_ID, new MessagePacket());
			$event->getClient()->setCustomHandler(new StarGateExtensionPacketHandler($event->getSession() ?? throw new AssumptionFailedError("Client session should not be null when authenticated"), $this));
		}, EventPriority::NORMAL, $this);
		self::$serverName = StarGateAtlantis::getInstance()->getDefaultClient()?->getClientName() ?? "";
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			foreach(StarGateAtlantis::getInstance()->getClients() as $client){
				$client->sendPacket(ServerStatPacket::create(
					self::$serverName,
					"127.0.0.1:" . $this->getServer()->getPort(),
					count($this->getServer()->getOnlinePlayers()),
					count($this->getServer()->getWorldManager()->getWorlds()),
					$this->getServer()->getTicksPerSecond(),
					$this->getServer()->getTicksPerSecondAverage(),
					$this->getServer()->getTick()
				));
			}
		}), 20 * 3);
	}

	public static function getServerName() : string{
		return self::$serverName;
	}

	public static function registerUpdateNotify(string $identifier, string $class, \Closure $handler) : void{
		if(!class_exists($class)){
			throw new AssumptionFailedError("Class $class does not exist");
		}
		$entry = new UpdateNotifyEntry();
		$entry->class = $class;
		$entry->handler = $handler;
		self::$updateNotifies[$identifier][] = $entry;
	}

	/** @phpstan-return list<UpdateNotifyEntry> */
	public static function getUpdateNotifyEntries(string $identifier) : array{
		return self::$updateNotifies[$identifier] ?? [];
	}

	public static function notifyUpdate(string $identifier, \JsonSerializable $object) : void{
		$data = Utils::assumeNotFalse(json_encode($object, JSON_INVALID_UTF8_SUBSTITUTE));
		foreach(StarGateAtlantis::getInstance()->getClients() as $_ => $client){
			$client->sendPacket(UpdateForwardPacket::create($identifier, $data));
		}
	}

	public static function broadcastPacket(StarGatePacket $packet): void{
		foreach(StarGateAtlantis::getInstance()->getClients() as $client){
			$client->sendPacket($packet);
		}
	}

	public static function isServerOnline(string $serverName) : bool{
		return array_any(self::$onlineServers, fn($entry) => $entry->name === $serverName);
	}
}
