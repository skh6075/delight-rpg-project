<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

final readonly class ServerEntry{

	public function __construct(
		public string $name,
		public string $address
	){}
}
