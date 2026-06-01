<?php

declare(strict_types=1);

namespace skh6075\ExtensionPlugin\utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\item\Item;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function base64_encode;
use function base64_decode;

final class ItemUtils{
	private const string NAMESPACE_AIR = "minecraft:air";

	private static ?LittleEndianNbtSerializer $serializer = null;

	private static function serializer(): LittleEndianNbtSerializer{
		return self::$serializer ??= new LittleEndianNbtSerializer();
	}

	public static function serialize(Item $item, ?int $count = null, int $slot = -1): string{
		if($item->isNull()){
			return "";
		}
		$item = clone $item;
		$item->setCount($count ?? $item->getCount());
		return base64_encode(self::serializer()->write(new TreeRoot($item->nbtSerialize($slot))));
	}

	public static function deserialize(string $buffer, int &$slot = -1): Item{
		if($buffer === ""){
			return VanillaBlocks::AIR()->asItem();
		}
		$nbt = self::serializer()->read(Utils::assumeNotFalse(base64_decode($buffer, true)))->mustGetCompoundTag();
		$slot = $nbt->getByte(SavedItemStackData::TAG_SLOT, -1);
		return Item::nbtDeserialize($nbt);
	}

	public static function getDisplayName(Item $item, bool $includeCount = true): string{
		return "§r§f" . $item->getName() . "§r§f" . ($includeCount ? " (§l§gx" . $item->getCount() . "§r§f)" : "");
	}

	public static function fastNamedTag(Item $item): CompoundTag{
		return \Closure::bind(fn(): CompoundTag => $this->nbt, $item, Item::class)->call($item);
	}

	public static function toStringId(Item $item): string{
		if($item->isNull()){
			return self::NAMESPACE_AIR;
		}
		return GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
	}

	public static function fromStringId(string $stringId, int $meta = 0, int $count = 1, ?CompoundTag $nbt = null): Item{
		if($stringId === self::NAMESPACE_AIR){
			return VanillaBlocks::AIR()->asItem();
		}
		return GlobalItemDataHandlers::getDeserializer()->deserializeStack(
			GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataString($stringId, $meta, $count, $nbt)
		);
	}
}