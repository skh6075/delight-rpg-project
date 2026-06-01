<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

use pocketmine\player\Player;

/**
 * @template T of BaseSession
 */
interface SessionProvider{

	/**
	 * Creates a session with handle.
	 * If player is null, returns OfflineSessionHandle (requires manual disposal).
	 * If player is provided, returns OnlineSessionHandle (SessionManager-managed).
	 *
	 * @param bool $createOnFailure Whether to create a new session if one does not already exist.
	 *
	 * @phpstan-return \Generator<mixed, mixed, mixed, SessionHandle<T>|null>
	 */
	public function createSession(string $xuid, ?Player $player = null, bool $createOnFailure = false) : \Generator;

	/**
	 * Gets the session for the given player or XUID.
	 *
	 * @phpstan-return T|null
	 */
	public function getSession(string|Player $playerOrXuid) : ?BaseSession;

	/**
	 * Removes the session for the given XUID.
	 *
	 * @phpstan-return \Generator<mixed, mixed, mixed, void>
	 */
	public function removeSession(string $xuid) : \Generator;
}
