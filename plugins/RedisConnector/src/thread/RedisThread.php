<?php

declare(strict_types=1);

namespace alvin0319\RedisConnector\thread;

use alvin0319\RedisConnector\operation\RedisOperation;
use alvin0319\RedisConnector\util\ThreadSafeSerializer;
use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\AttachableThreadSafeLogger;
use pocketmine\thread\Thread;
use Predis\Client;
use function count;
use function strtoupper;
use function usleep;

final class RedisThread extends Thread{

	private bool $running = true;
	private bool $connected = false;
	private bool $connectionFailed = false;

	public function __construct(
		private readonly AttachableThreadSafeLogger $logger,
		private ThreadSafeArray $incoming,
		private ThreadSafeArray $outgoing, // @phpstan-ignore-line
		private readonly string $autoloaderPath,
		private readonly string $host,
		private readonly int $port,
		private readonly float $connectTimeout,
		private readonly float $readWriteTimeout
	){}

	protected function onRun() : void{
		$logger = new \PrefixedLogger($this->logger, "Redis Thread");
		try{
			require $this->autoloaderPath;
			$client = new Client([
				"host" => $this->host,
				"port" => $this->port,
				"scheme" => "tcp",
				"timeout" => $this->connectTimeout,
				"read_write_timeout" => $this->readWriteTimeout
			]);

			$client->connect();
			$logger->info("Connected to the redis");
			$this->synchronized(function() : void{
				$this->connected = true;
			});
		}catch(\Exception $e) {
			$logger->error("Unable to connect to the redis");
			$logger->logException($e);
			$this->synchronized(function() : void{
				$this->connectionFailed = true;
			});
			$this->stop();
			return;
		}catch(\Throwable $e){
			$logger->error("Redis thread crashed before startup completed");
			$logger->logException($e);
			$this->markConnectionFailed();
			$this->stop();
			return;
		}

		try{
			while($this->synchronized(function() : bool{ return $this->running; })){
				if(count($this->incoming) > 0){
					$operation = $this->incoming->shift();
					if($operation instanceof RedisOperation){
						$this->handleOperation($client, $operation);
					}
				}
				usleep(1000);
			}
		}catch(\Throwable $e){
			$logger->error("Redis thread crashed while processing commands");
			$logger->logException($e);
		}finally{
			$client->disconnect();
		}
	}

	private function handleOperation(Client $client, RedisOperation $operation) : void{
		$queryId = $operation->getId();
		$command = strtoupper($operation->getCommand());
		$args = $operation->getArgsArray();

		try{
			$result = $this->executeCommand($client, $command, $args);

			$this->outgoing[] = ThreadSafeArray::fromArray([
				"type" => "success",
				"id" => $queryId,
				"result" => ThreadSafeSerializer::encode($result)
			]);

		}catch(\Throwable $e){
			$this->outgoing[] = ThreadSafeArray::fromArray([
				"type" => "error",
				"id" => $queryId,
				"message" => $e->getMessage()
			]);
		}
	}

	/**
	 * @param list<mixed> $args
	 * @return mixed
	 */
	private function executeCommand(Client $client, string $command, array $args) : mixed{
		return $client->executeRaw([...[$command], ...$args]);
	}

	public function addOperation(RedisOperation $operation) : void{
		$this->incoming[] = $operation;
	}

	public function waitUntilConnectionMade(int $timeoutMs = 5000) : bool{
		$elapsedMs = 0;

		while($elapsedMs < $timeoutMs){
			$result = $this->synchronized(function() : array{
				return [
					"connected" => $this->connected,
					"failed" => $this->connectionFailed
				];
			});

			if($result["connected"]){
				return true;
			}

			if($result["failed"]){
				return false;
			}

			usleep(10000);
			$elapsedMs += 10;
		}

		$this->markConnectionFailed();
		return false;
	}

	private function markConnectionFailed() : void{
		$this->synchronized(function() : void{
			$this->connectionFailed = true;
		});
	}

	public function stop() : void{
		$this->synchronized(function() : void{
			$this->running = false;
		});
	}
}
