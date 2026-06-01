<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use alemiz\sga\codec\StarGatePacketHandler;
use alemiz\sga\protocol\StarGatePacket;
use alemiz\sga\protocol\types\PacketHelper;

final class MessagePacket extends StarGatePacket{
	public const int PACKET_ID = 53;

	private const int TARGET_BROADCAST = 0;
	private const int TARGET_WHISPER = 1;

	public const int MESSAGE_CHAT = 0;
	public const int MESSAGE_POPUP = 1;
	public const int MESSAGE_TIP = 2;
	public const int MESSAGE_ACTION_BAR = 3;
	public const int MESSAGE_TOAST = 4;
	public const int MESSAGE_TITLE = 5;

	public int $targetType;
	public int $messageType;
	public string $content;
	public string $subContent = "";
	public string $targetId = ""; //xuid or name

	public function encodePayload() : void{
		PacketHelper::writeInt($this, $this->targetType);
		PacketHelper::writeInt($this, $this->messageType);
		PacketHelper::writeString($this, $this->content);
		if($this->targetType === self::TARGET_WHISPER){
			PacketHelper::writeString($this, $this->targetId);
		}
		if($this->messageType >= self::MESSAGE_TOAST){
			PacketHelper::writeString($this, $this->subContent);
		}
	}

	public function decodePayload() : void{
		$this->targetType = PacketHelper::readInt($this);
		$this->messageType = PacketHelper::readInt($this);
		$this->content = PacketHelper::readString($this);
		if($this->targetType === self::TARGET_WHISPER){
			$this->targetId = PacketHelper::readString($this);
		}
		if($this->messageType >= self::MESSAGE_TOAST){
			$this->subContent = PacketHelper::readString($this);
		}
	}

	public function getPacketId() : int{
		return self::PACKET_ID;
	}

	public function handle(StarGatePacketHandler $handler) : bool{
		return $handler instanceof StarGateExtensionPacketHandler ? $handler->handleMessage($this) : false;
	}

	private static function create(?string $targetId = null): self{
		$result = new self;
		$result->targetType = $targetId == null ? self::TARGET_BROADCAST : self::TARGET_WHISPER;
		if($result->targetType === self::TARGET_WHISPER) {
			if($targetId === null){
				throw new \InvalidArgumentException("targetId cannot be null when targetType is TARGET_WHISPER");
			}
			$result->targetId = $targetId;
		}
		return $result;
	}

	public static function chat(string $content, ?string $targetId = null): self{
		$result = self::create($targetId);
		$result->messageType = self::MESSAGE_CHAT;
		$result->content = $content;
		return $result;
	}

	public static function popup(string $content, ?string $targetId = null): self{
		$result = self::create($targetId);
		$result->messageType = self::MESSAGE_POPUP;
		$result->content = $content;
		return $result;
	}

	public static function tip(string $content, ?string $targetId = null): self{
		$result = self::create($targetId);
		$result->messageType = self::MESSAGE_TIP;
		$result->content = $content;
		return $result;
	}

	public static function actionBar(string $content, ?string $targetId = null): self{
		$result = self::create($targetId);
		$result->messageType = self::MESSAGE_ACTION_BAR;
		$result->content = $content;
		return $result;
	}

	public static function toast(string $content, string $subContent = "", ?string $targetId = null): self{
		$result = self::create($targetId);
		$result->messageType = self::MESSAGE_TOAST;
		$result->content = $content;
		$result->subContent = $subContent;
		return $result;
	}

	public static function title(string $content, string $subContent = "", ?string $targetId = null): self{
		$result = self::create($targetId);
		$result->messageType = self::MESSAGE_TITLE;
		$result->content = $content;
		$result->subContent = $subContent;
		return $result;
	}
}