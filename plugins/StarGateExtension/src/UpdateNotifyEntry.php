<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

final class UpdateNotifyEntry{

	/** @phpstan-var class-string */
	public string $class;

	/** @var \Closure(object) : void */
	public \Closure $handler;
}
