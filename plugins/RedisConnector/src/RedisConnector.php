<?php

declare(strict_types=1);

namespace alvin0319\RedisConnector;

use alvin0319\DependencyLoader\DependencyLoader;
use alvin0319\RedisConnector\operation\RedisOperation;
use alvin0319\RedisConnector\util\ThreadSafeSerializer;
use alvin0319\RedisConnector\thread\RedisThread;
use pmmp\thread\ThreadSafeArray;
use pocketmine\plugin\PluginBase;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use function count;
use function uniqid;

final class RedisConnector extends PluginBase{
	use SingletonTrait;

	private const int DEFAULT_CONNECTION_TIMEOUT_MS = 5000;
	private const float DEFAULT_SOCKET_TIMEOUT_SECONDS = 5.0;

	private ?RedisThread $redisThread = null;
	private ThreadSafeArray $incoming;
	private ThreadSafeArray $outgoing;
	/** @phpstan-var array<string, PromiseResolver<mixed>> */
	private array $pendingQueries = [];

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$config = $this->getConfig();
		$host = $config->get("host", "127.0.0.1");
		$port = $config->get("port", 6379);
		$connectionTimeoutMs = (int) $config->get("connection-timeout-ms", self::DEFAULT_CONNECTION_TIMEOUT_MS);
		$socketTimeout = (float) $config->get("socket-timeout", self::DEFAULT_SOCKET_TIMEOUT_SECONDS);

		$this->incoming = new ThreadSafeArray();
		$this->outgoing = new ThreadSafeArray();

		$this->redisThread = new RedisThread(
			$this->getServer()->getLogger(),
			$this->incoming,
			$this->outgoing,
			DependencyLoader::getAutoloaderPath(),
			$host,
			$port,
			$connectionTimeoutMs / 1000,
			$socketTimeout
		);

		$this->redisThread->start();

		if(!$this->redisThread->waitUntilConnectionMade($connectionTimeoutMs)){
			$this->getLogger()->error("Failed to connect to Redis server within timeout");
			$this->redisThread->stop();
			$this->redisThread->join();
			$this->redisThread = null;
			return;
		}

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->processResults();
		}), 1);
	}

	protected function onDisable() : void{
		if($this->redisThread !== null){
			$this->redisThread->stop();
			$this->redisThread->join();
		}
	}

	private function processResults() : void{
		while(count($this->outgoing) > 0){
			$result = $this->outgoing->shift();
			if($result !== null){
				$resultData = ThreadSafeSerializer::decodeArray($result);
				$queryId = $resultData["id"] ?? null;

				if($queryId !== null && isset($this->pendingQueries[$queryId])){
					$resolver = $this->pendingQueries[$queryId];
					unset($this->pendingQueries[$queryId]);

					if($resultData["type"] === "success"){
						$resolver->resolve($resultData["result"]);
					}else{
						$resolver->reject();
					}
				}
			}
		}
	}

	/**
	 * @param string $command
	 * @param list<mixed> $arguments
	 * @phpstan-return Promise<mixed>
	 */
	public function execute(string $command, array $arguments) : Promise{
		/** @phpstan-var PromiseResolver<mixed> $resolver */
		$resolver = new PromiseResolver();

		if($this->redisThread === null){
			$resolver->reject();
			return $resolver->getPromise();
		}

		$operation = new RedisOperation(uniqid(), $command, $arguments);
		$this->incoming[] = $operation;
		$this->pendingQueries[$operation->getId()] = $resolver;

		return $resolver->getPromise();
	}

	/**
	 * Set a Redis key-value pair
	 * @phpstan-return Promise<string> Promise that resolves to the Redis SET command result
	 */
	public function set(string $key, string $value) : Promise{
		return $this->execute("SET", [$key, $value]);
	}

	/**
	 * Get a Redis value by key
	 * @phpstan-return Promise<string|null> Promise that resolves to the Redis value or null if key doesn't exist
	 */
	public function get(string $key) : Promise{
		return $this->execute("GET", [$key]);
	}

	/**
	 * Delete Redis keys
	 * @phpstan-param list<string> $keys Array of keys to delete
	 * @phpstan-return Promise<int> Promise that resolves to the number of keys deleted
	 */
	public function del(array $keys) : Promise{
		return $this->execute("DEL", $keys);
	}

	/**
	 * Set a Redis hash field-value pair
	 * @phpstan-return Promise<int> Promise that resolves to the Redis HSET command result
	 */
	public function hashSet(string $key, string $field, string $value) : Promise{
		return $this->execute("HSET", [$key, $field, $value]);
	}

	/**
	 * Get a Redis hash field value
	 * @phpstan-return Promise<string|null> Promise that resolves to the Redis hash field value or null if field doesn't exist
	 */
	public function hashGet(string $key, string $field) : Promise{
		return $this->execute("HGET", [$key, $field]);
	}

	/**
	 * Delete a Redis hash field
	 * @phpstan-return Promise<int> Promise that resolves to the number of fields deleted (0 or 1)
	 */
	public function hashDelete(string $key, string $field) : Promise{
		return $this->execute("HDEL", [$key, $field]);
	}

	/**
	 * Get all fields and values from a Redis hash
	 * @phpstan-return Promise<array<string, string>> Promise that resolves to an associative array of field-value pairs
	 */
	public function hashGetAll(string $key) : Promise{
		return $this->execute("HGETALL", [$key]);
	}

	/**
	 * Set a key only if it doesn't exist with expiration (atomic lock acquisition)
	 * @phpstan-return Promise<string|null> Promise that resolves to OK if key was set, null if already exists
	 */
	public function setNxEx(string $key, string $value, int $ttl) : Promise{
		return $this->execute("SET", [$key, $value, "EX", $ttl, "NX"]);
	}

	/**
	 * Check if a key exists
	 * @phpstan-return Promise<int> Promise that resolves to 1 if exists, 0 if not
	 */
	public function exists(string $key) : Promise{
		return $this->execute("EXISTS", [$key]);
	}

	/**
	 * Atomically increment a key
	 * @phpstan-return Promise<int> Promise that resolves to the new value after increment
	 */
	public function incr(string $key) : Promise{
		return $this->execute("INCR", [$key]);
	}

	/**
	 * Fetches multiple keys efficiently
	 *
	 * @phpstan-param list<string> $keys
	 *
	 * @phpstan-return Promise<list<string|null>>
	 */
	public function mget(array $keys) : Promise{
		return $this->execute("MGET", [$keys]);
	}
}
