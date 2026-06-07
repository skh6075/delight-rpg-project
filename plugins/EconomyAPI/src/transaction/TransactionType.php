<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\transaction;

enum TransactionType : string{
	case ADD = "add";
	case DEDUCT = "deduct";
	case SET = "set";
	case PAY = "pay";
}