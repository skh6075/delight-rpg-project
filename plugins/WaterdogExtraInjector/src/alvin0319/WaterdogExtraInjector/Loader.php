<?php

declare(strict_types=1);

namespace alvin0319\WaterdogExtraInjector;

use alvin0319\WaterdogExtraInjector\network\handler\WDPELoginPacketHandler;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\encryption\EncryptionContext;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class Loader extends PluginBase{

	/** @phpstan-var \WeakMap<NetworkSession, PlayerInfo> */
	public static \WeakMap $xuidMap;

	protected function onEnable() : void{
		self::$xuidMap = new \WeakMap();
		$this->saveDefaultConfig();
		if(!$this->getConfig()->get("enabled", false)){
			return;
		}
		EncryptionContext::$ENABLED = false;
		$this->getServer()->getPluginManager()->registerEvent(DataPacketReceiveEvent::class, function(DataPacketReceiveEvent $event) : void{
			$packet = $event->getPacket();
			if(!$packet instanceof LoginPacket){
				return;
			}
			$event->getOrigin()->setHandler(new WDPELoginPacketHandler($this->getServer(), $event->getOrigin(),
				function(PlayerInfo $info) use ($event) : void{
					\Closure::bind(function(PlayerInfo $info) : void{
						$this->info = $info;
						if($info instanceof XboxLivePlayerInfo){
							$this->logger->debug("XUID: " . $info->getXuid());
						}
						Loader::$xuidMap[$this] = $info;
						$this->logger->info("Player: " . TextFormat::AQUA . $info->getUsername() . TextFormat::RESET);
						$this->logger->setPrefix($this->getLogPrefix());
					}, $event->getOrigin(), NetworkSession::class)->call($event->getOrigin(), $info);
				}, function(bool $isAuthenticated, bool $authRequired, string|Translatable|null $error, ?string $clientPubKey) use ($event) : void{
					\Closure::bind(
						closure: function(NetworkSession $session) use ($isAuthenticated, $authRequired, $error, $clientPubKey) : void{
							$session->setAuthenticationStatus($isAuthenticated, $authRequired, $error, $clientPubKey);
							if(!isset(Loader::$xuidMap[$session])){
								throw new PacketHandlingException("PlayerInfo not set");
							}
							$session->info = Loader::$xuidMap[$session];
							unset(Loader::$xuidMap[$session]);
						},
						newThis: $this,
						newScope: NetworkSession::class
					)($event->getOrigin());
				}));
		}, EventPriority::LOWEST, $this, true);
	}
}