<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use alemiz\sga\codec\StarGatePacketHandler;
use alemiz\sga\protocol\StarGatePacket;
use alemiz\sga\protocol\types\PacketHelper;

final class UpdateForwardPacket extends StarGatePacket{

	public const int PACKET_ID = 50;

	public string $identifier {
		get{
			return $this->identifier;
		}
	}
	public string $payload {
		get{
			return $this->payload;
		}
	}

	public static function create(string $identifier, string $payload) : UpdateForwardPacket{
		$self = new self();
		$self->identifier = $identifier;
		$self->payload = $payload;
		return $self;
	}

	public function encodePayload() : void{
		PacketHelper::writeString($this, $this->identifier);
		PacketHelper::writeString($this, $this->payload);
	}

	public function decodePayload() : void{
		$this->identifier = PacketHelper::readString($this);
		$this->payload = PacketHelper::readString($this);
	}

	public function getPacketId() : int{
		return self::PACKET_ID;
	}

	public function handle(StarGatePacketHandler $handler) : bool{
		return $handler instanceof StarGateExtensionPacketHandler ? $handler->handleUpdateForward($this) : false;
	}
}
