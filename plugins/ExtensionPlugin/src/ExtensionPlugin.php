<?php

declare(strict_types=1);

namespace skh6075\ExtensionPlugin;

use Closure;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\promise\Promise;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use SOFe\AwaitGenerator\Await;
use function is_string;
use function explode;
use function count;
use function round;
use function mt_rand;
use function mt_getrandmax;
use function max;
use function min;
use function is_subclass_of;
use function strtolower;

function assumeNotNull(mixed $value, Closure|string $message = "This should never be null"): mixed{
	if($value === null){
		throw new AssumptionFailedError(is_string($message) ? $message : $message());
	}
	return $value;
}

function posToString(Vector3 $pos): string{
	$result = implode(":", [$pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()]);
	if($pos instanceof Position){
		$result .= ":" . $pos->getWorld()->getFolderName();
	}
	return $result;
}

function stringToPos(string $data): Vector3{
	$parts = explode(":", $data);
	if(count($parts) === 3){
		return new Vector3((int) $parts[0], (int) $parts[1], (int) $parts[2]);
	}elseif(count($parts) === 4){
		return new Position((int) $parts[0], (int) $parts[1], (int) $parts[2], Server::getInstance()->getWorldManager()->getWorldByName($parts[3]));
	}else{
		throw new \InvalidArgumentException("Invalid position data");
	}
}

function random_float(float $min, float $max, int $round = 2): float{
	return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $round);
}

function percentage(int|float $min, int|float $max, int|float $current, int $round = 2): float{
	return max(0.0, min(100.0, round((($current - $min) / ($max - $min)) * 100, $round)));
}

final class ExtensionPlugin extends PluginBase{
	use SingletonTrait;

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->saveDefaultConfig();
	}

	public static function makePHPStanHappy(): void{
		//NOOP
	}

	/** @param array<string, mixed> $sqlMap */
	public static function createConnector(PluginBase $plugin, array $sqlMap = []) : DataConnector{
		$sqlMap = count($sqlMap) === 0 ? ["mysql" => "mysql.sql", "sqlite" => "sqlite.sql"] : $sqlMap;
		if(self::getInstance()->getConfig()->get("force-use-setting")){
			return libasynql::create($plugin, self::getInstance()->getConfig()->get("database"), $sqlMap);
		}
		return libasynql::create($plugin, $plugin->getConfig()->get("database"), $sqlMap);
	}

	/**
	 * @template T of mixed
	 * @phpstan-param Promise<T> $promise
	 *
	 * @phpstan-return \Generator<mixed, mixed, mixed, T>
	 *
	 * @throws \RuntimeException
	 */
	public static function promiseToAwait(Promise $promise): \Generator{
		return yield from Await::promise(function($resolve, $reject) use ($promise): void{
			$promise->onCompletion(static fn($value) => $resolve($value), static fn() => $reject(new \RuntimeException("Promise rejected")));
		});
	}

	/**
	 * @template T of \UnitEnum
	 * @phpstan-param class-string<T> $class
	 *
	 * @phpstan-return T|null
	 */
	public static function fromEnumName(string $class, string $caseName): ?object{
		if(!is_subclass_of($class, \UnitEnum::class)){ // @phpstan-ignore-line
			throw new AssumptionFailedError("Class is not an instance of UnitEnum");
		}
		$cases = $class::cases();
		$caseName = strtolower($caseName);
		return array_find($cases, static fn($case) => strtolower($case->name) === $caseName);
	}

	public static function findPlayerByXuid(string $xuid) : ?Player{
		return array_find(Server::getInstance()->getOnlinePlayers(), fn(Player $player) => $player->getXuid() === $xuid);
	}
}