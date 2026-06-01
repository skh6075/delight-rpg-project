<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use alemiz\sga\codec\StarGatePacketHandler;
use alemiz\sga\protocol\StarGatePacket;
use function count;
use function strlen;

final class OnlineServerListPacket extends StarGatePacket{

	public const int PACKET_ID = 51;

	/** @phpstan-var list<ServerEntry> */
	private array $servers = [];

	/** @phpstan-param list<ServerEntry> $servers */
	public static function create(array $servers) : self{
		$result = new self();
		$result->servers = $servers;
		return $result;
	}

	/** @phpstan-return list<ServerEntry> */
	public function getServers() : array{
		return $this->servers;
	}

	public function encodePayload() : void{
		$this->putInt(count($this->servers));
		foreach($this->servers as $server){
			$this->putInt(strlen($server->name));
			$this->put($server->name);
			$this->putInt(strlen($server->address));
			$this->put($server->address);
		}
	}

	public function decodePayload() : void{
		$len = $this->getInt();
		for($i = 0; $i < $len; $i++){
			$this->servers[] = new ServerEntry(
				$this->get($this->getInt()),
				$this->get($this->getInt())
			);
		}
	}

	public function getPacketId() : int{
		return self::PACKET_ID;
	}

	public function handle(StarGatePacketHandler $handler) : bool{
		return $handler instanceof StarGateExtensionPacketHandler ? $handler->handleOnlineServerList($this) : false;
	}
}
