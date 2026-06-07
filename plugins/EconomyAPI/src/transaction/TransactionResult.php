<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\transaction;

enum TransactionResult : string{
	case SUCCESS = "성공";
	case INSUFFICIENT_FUNDS = "잔액이 부족합니다.";
	case ACCOUNT_NOT_FOUND = "해당 플레이어를 찾을 수 없습니다.";
	case UNKNOWN_ERROR = "알 수 없는 오류가 발생했습니다.";
	case OVERFLOW = "잔액이 최대 한도를 초과합니다.";
	case TRANSACTION_FAILED = "거래가 불가능한 화폐입니다.";
	case CANCELLED = "거래가 취소되었습니다.";
}