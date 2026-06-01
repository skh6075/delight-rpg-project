<?php

declare(strict_types=1);

namespace skh6075\ExtensionPlugin;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\Sound;

final class Message{
	public static function stringify(string $text, Unicodes $unicode = Unicodes::INFO): string{
		return $unicode->toString() . " " . $text . TextFormat::RESET;
	}

	private static function send(CommandSender $sender, string $text, Unicodes $unicode, ?Sound $sound = null): void{
		$sender->sendMessage(self::stringify($text, $unicode));
		if($sound !== null && $sender instanceof Player){
			$sender->getWorld()->addSound($sender->getPosition(), $sound, [$sender]);
		}
	}

	public static function warn(CommandSender $sender, string $text, ?Sound $sound = null): void{
		self::send($sender, TextFormat::RED . $text, Unicodes::WARN, $sound);
	}

	public static function info(CommandSender $sender, string $text, ?Sound $sound = null): void{
		self::send($sender, TextFormat::GRAY . $text, Unicodes::INFO, $sound);
	}

	public static function alert(CommandSender $sender, string $text, ?Sound $sound = null): void{
		self::send($sender, TextFormat::YELLOW . $text, Unicodes::ALERT, $sound);
	}

	public static function log(CommandSender $sender, string $text, ?Sound $sound = null): void{
		self::send($sender, TextFormat::MATERIAL_AMETHYST . $text, Unicodes::LOG, $sound);
	}

	public static function success(CommandSender $sender, string $text, ?Sound $sound = null): void{
		self::send($sender, TextFormat::GREEN . $text, Unicodes::SUCCESS, $sound);
	}

	public static function notice(CommandSender $sender, string $text, ?Sound $sound = null): void{
		self::send($sender, TextFormat::AQUA . $text, Unicodes::NOTICE, $sound);
	}

	public static function sendCommandRootError(Command $command, CommandSender $sender, \Throwable $throwable): void{
		$sender->getServer()->getLogger()->logException($throwable);
		self::warn($sender, "§f{$command->getName()}§c 명령어 작업에 실패했습니다: §f" . $throwable->getMessage());
	}

	public static function sendPlayerOnlyMessage(CommandSender $sender): void{
		self::warn($sender, "인 게임에서만 사용할 수 있습니다.");
	}

	public static function sendTargetNotFounded(CommandSender $sender): void{
		self::alert($sender, "해당 플레이어를 찾을 수 없습니다.");
	}

	public static function sendSessionNotLoaded(CommandSender $sender): void{
		self::warn($sender, "데이터를 불러오고 있습니다. 잠시 후 다시 시도해주세요!");
	}
}