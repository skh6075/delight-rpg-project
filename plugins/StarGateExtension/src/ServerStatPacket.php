<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use alemiz\sga\codec\StarGatePacketHandler;
use alemiz\sga\protocol\StarGatePacket;
use alemiz\sga\protocol\types\PacketHelper;

final class ServerStatPacket extends StarGatePacket{

	public const int PACKET_ID = 52;

	private string $name;
	private string $address;
	private int $players;
	private int $worlds;
	private float $tps;
	private float $avgTps;
	private int $uptime;

	public static function create(string $name, string $address, int $players, int $worlds, float $tps, float $avgTps, int $uptime) : ServerStatPacket{
		$self = new self();
		$self->name = $name;
		$self->address = $address;
		$self->players = $players;
		$self->worlds = $worlds;
		$self->tps = $tps;
		$self->avgTps = $avgTps;
		$self->uptime = $uptime;
		return $self;
	}

	public function getName() : string{
		return $this->name;
	}

	public function getAddress() : string{
		return $this->address;
	}

	public function getPlayers() : int{
		return $this->players;
	}

	public function getWorlds() : int{
		return $this->worlds;
	}

	public function getTps() : float{
		return $this->tps;
	}

	public function getAvgTps() : float{
		return $this->avgTps;
	}

	public function getUptime() : int{
		return $this->uptime;
	}

	public function encodePayload() : void{
		PacketHelper::writeString($this, $this->name);
		PacketHelper::writeString($this, $this->address);
		PacketHelper::writeInt($this, $this->players);
		PacketHelper::writeInt($this, $this->worlds);
		$this->putFloat($this->tps);
		$this->putFloat($this->avgTps);
		PacketHelper::writeLong($this, $this->uptime);
	}

	public function decodePayload() : void{
		$this->name = PacketHelper::readString($this);
		$this->address = PacketHelper::readString($this);
		$this->players = PacketHelper::readInt($this);
		$this->worlds = PacketHelper::readInt($this);
		$this->tps = $this->getFloat();
		$this->avgTps = $this->getFloat();
		$this->uptime = PacketHelper::readLong($this);
	}

	public function getPacketId() : int{
		return self::PACKET_ID;
	}

	public function handle(StarGatePacketHandler $handler) : bool{
		return $handler instanceof StarGateExtensionPacketHandler ? $handler->handleServerStat($this) : false;
	}
}
