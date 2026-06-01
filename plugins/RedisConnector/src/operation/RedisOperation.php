<?php

declare(strict_types=1);

namespace alvin0319\RedisConnector\operation;

use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use alvin0319\RedisConnector\util\ThreadSafeSerializer;

final class RedisOperation extends ThreadSafe{

	protected ThreadSafeArray $args;

	/** @param list<mixed> $args */
	public function __construct(protected readonly string $id, protected readonly string $command, array $args = []){
		$this->args = ThreadSafeSerializer::encodeArray($args);
	}

	public function getId() : string{
		return $this->id;
	}

	public function getCommand() : string{
		return $this->command;
	}

	public function getArgs() : ThreadSafeArray{
		return $this->args;
	}

	/** @return list<mixed> */
	public function getArgsArray() : array{
		/** @var list<mixed> $args */
		$args = ThreadSafeSerializer::decodeArray($this->args);
		return $args;
	}
}
