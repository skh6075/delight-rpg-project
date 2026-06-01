<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use alemiz\sga\client\ClientSession;
use alemiz\sga\handler\SessionHandler;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\SetTitlePacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\ToastRequestPacket;
use pocketmine\player\Player;
use function json_decode;

final class StarGateExtensionPacketHandler extends SessionHandler{

	public function __construct(
		ClientSession $session,
		private readonly StarGateExtension $plugin
	){
		parent::__construct($session);
	}

	public function handleUpdateForward(UpdateForwardPacket $packet) : bool{
		if(($entries = StarGateExtension::getUpdateNotifyEntries($packet->identifier)) !== []){
			foreach($entries as $entry){
				try{
					$mapper = new \JsonMapper();
					$mapper->bEnforceMapType = false;
					$mapper->bExceptionOnMissingData = true;
					$class = $mapper->map(json_decode($packet->payload, true), $entry->class);
					$handler = $entry->handler;
					$handler($class); // @phpstan-ignore-line
				}catch(\JsonMapper_Exception $e){
					$this->plugin->getLogger()->critical("An error occurred whilst deserializing json data:");
					$this->plugin->getLogger()->logException($e);
					return false;
				}catch(\Throwable $e){
					$this->plugin->getLogger()->critical("An unknown error occurred:");
					$this->plugin->getLogger()->logException($e);
				}
			}
		}else{
			$this->plugin->getLogger()->warning("Received unknown update notify packet with identifier " . $packet->identifier);
		}
		return true;
	}

	public function handleOnlineServerList(OnlineServerListPacket $packet) : bool{
		StarGateExtension::$onlineServers = $packet->getServers();
		return true;
	}

	public function handleServerStat(ServerStatPacket $packet) : bool{
		$stat = ServerStat::fromPacket($packet);
		StarGateExtension::$serverStats[$stat->name] = $stat;
		return true;
	}

	public function handleMessage(MessagePacket $packet): bool{
		$recipients = $this->plugin->getServer()->getOnlinePlayers();
		if($packet->targetId !== ""){
			$target = array_filter($recipients, static fn(Player $player): bool =>
				strtolower($player->getName()) === strtolower($packet->targetId) ||
				$player->getXuid() === $packet->targetId
			);
			if($target !== []){
				$recipients = $target;
			}
		}
		/** @phpstan-var list<ClientboundPacket> $bedrockPackets */
		// @phpstan-ignore-next-line
		$bedrockPackets = match ($packet->messageType) {
			MessagePacket::MESSAGE_CHAT => [TextPacket::raw($packet->content)],
			MessagePacket::MESSAGE_POPUP => [TextPacket::popup($packet->content)],
			MessagePacket::MESSAGE_TIP => [TextPacket::tip($packet->content)],
			MessagePacket::MESSAGE_ACTION_BAR => [SetTitlePacket::actionBarMessage($packet->content)],
			MessagePacket::MESSAGE_TOAST => [ToastRequestPacket::create($packet->content, $packet->subContent)],
			MessagePacket::MESSAGE_TITLE => [SetTitlePacket::title($packet->content), SetTitlePacket::subtitle($packet->subContent)],
			default => throw new \InvalidArgumentException("Unknown message type: " . $packet->messageType)
		};
		NetworkBroadcastUtils::broadcastPackets($recipients, $bedrockPackets);
		return true;
	}
}
