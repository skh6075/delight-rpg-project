<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI\event;

use alvin0319\EconomyAPI\session\EconomySession;
use alvin0319\EconomyAPI\transaction\Transaction;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;

final class EconomyTransactionEvent extends Event implements Cancellable{
	use CancellableTrait;

	private string $reason = "";

	public function __construct(
		public readonly EconomySession $session,
		public readonly Transaction $transaction
	){}

	public function getReason() : string{
		return $this->reason;
	}

	public function setReason(string $reason) : void{
		$this->reason = $reason;
	}
}