<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

use pocketmine\plugin\Plugin;
use pocketmine\Server;

/**
 * Handle for offline sessions.
 * Automatically promotes to active session if player logs in.
 *
 * @template T of BaseSession
 * @extends SessionHandle<T>
 */
final class OfflineSessionHandle extends SessionHandle{

	private bool $disposed = false;
	private SessionLifecycleState $state = SessionLifecycleState::ACTIVE;

	/**
	 * @phpstan-param T $session
	 * @phpstan-param SessionProvider<T> $provider
	 */
	public function __construct(
		private readonly BaseSession $session,
		private readonly SessionProvider $provider,
		private readonly Plugin $plugin
	){
	}

	/**
	 * Get the underlying session.
	 *
	 * @phpstan-return T
	 * @throws \LogicException if session was already disposed
	 */
	public function getSession() : BaseSession{
		if($this->state === SessionLifecycleState::DISPOSED){
			throw new \LogicException("Cannot access disposed session for xuid: " . $this->session->getXuid());
		}
		return $this->session;
	}

	public function isDisposed() : bool{
		return $this->disposed;
	}

	public function isPromoted() : bool{
		return $this->state === SessionLifecycleState::PROMOTED || $this->session->isOnline();
	}

	public function getLifecycleState() : SessionLifecycleState{
		if($this->session->isOnline() && $this->state === SessionLifecycleState::ACTIVE){
			return SessionLifecycleState::PROMOTED;
		}
		return $this->state;
	}

	/**
	 * Save and dispose this offline session.
	 * Safe to call even if session was promoted (no-op in that case).
	 *
	 * @phpstan-return \Generator<mixed, mixed, mixed, void>
	 */
	public function dispose() : \Generator{
		if($this->disposed){
			return;
		}

		if($this->state === SessionLifecycleState::PROMOTED || $this->session->isOnline()){
			$this->disposed = true;
			$this->state = SessionLifecycleState::DISPOSED;
			Server::getInstance()->getLogger()->debug(
				"Skipping dispose for promoted session (xuid: {$this->session->getXuid()})"
			);
			return;
		}

		$this->disposed = true;
		$this->state = SessionLifecycleState::DISPOSED;

		try{
			yield from $this->provider->removeSession($this->session->getXuid());
		}catch(\Throwable $e){
			Server::getInstance()->getLogger()->logException($e);
		}
	}

	public function __destruct(){
		if(!$this->disposed && $this->state !== SessionLifecycleState::PROMOTED && !$this->session->isOnline()){
			Server::getInstance()->getLogger()->warning(
				"OfflineSessionHandle for xuid " . $this->session->getXuid() .
				" was not disposed properly! This may cause memory leaks. " .
				"Plugin: " . $this->plugin->getName()
			);
		}
	}
}
