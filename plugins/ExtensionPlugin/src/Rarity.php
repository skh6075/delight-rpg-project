<?php

declare(strict_types=1);

namespace skh6075\ExtensionPlugin;

use pocketmine\utils\LegacyEnumShimTrait;
use pocketmine\utils\TextFormat;

/**
 * @method static Rarity NORMAL()
 * @method static Rarity RARE()
 * @method static Rarity EPIC()
 * @method static Rarity UNIQUE()
 * @method static Rarity LEGENDARY()
 * @method static Rarity MYTHIC()
 *
 * @phpstan-type TMetadata array{0: string, 1: string, 2: Unicodes}
 */
enum Rarity{
	use LegacyEnumShimTrait;

	case NORMAL;
	case RARE;
	case EPIC;
	case UNIQUE;
	case LEGENDARY;
	case MYTHIC;

	/**
	 * @phpstan-return TMetadata
	 */
	private static function meta(string $displayName, string $color, Unicodes $icon): array{
		return [$displayName, $color, $icon];
	}

	/**
	 * @phpstan-return TMetadata
	 */
	private function getMetadata(): array{
		/** @phpstan-var array<int, TMetadata> $cache */
		static $cache = [];

		return $cache[spl_object_id($this)] ??= match($this){
			self::NORMAL => self::meta("노말", TextFormat::GRAY, Unicodes::NORMAL),
			self::RARE => self::meta("레어", TextFormat::AQUA, Unicodes::RARE),
			self::EPIC => self::meta("에픽", TextFormat::LIGHT_PURPLE, Unicodes::EPIC),
			self::UNIQUE => self::meta("유니크", TextFormat::GOLD, Unicodes::UNIQUE),
			self::LEGENDARY => self::meta("전설", TextFormat::YELLOW, Unicodes::LEGENDARY),
			self::MYTHIC => self::meta("신화", TextFormat::RED, Unicodes::MYTHIC)
		};
	}

	public function getDisplayName(): string{
		return $this->getMetadata()[0];
	}

	public function getDisplayColorizeName(): string{
		return $this->getMetadata()[1] . $this->getDisplayName();
	}

	public function getIcon(): string{
		return $this->getMetadata()[2]->toString();
	}
}