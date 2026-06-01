<?php

declare(strict_types=1);

namespace skh6075\ExtensionPlugin;

use function hexdec;
use function mb_chr;

enum Unicodes : string {
	case WARN = "0xE000";
	case ALERT = "0xE001";
	case SUCCESS = "0xE002";
	case NOTICE = "0xE003";
	case LOG = "0xE004";
	case INFO = "0xE005";

	case BOX_GOLD = "0xE006";
	case GOLD = "0xE007";
	case BOX_CRYSTAL = "0xE008";
	case CRYSTAL = "0xE009";
	case BOX_RUBY = "0xE00A";
	case RUBY = "0xE00B";

	public function toString(): string {
		static $cache = [];
		return $cache[$this->name] ??= mb_chr((int) hexdec($this->value));
	}
}