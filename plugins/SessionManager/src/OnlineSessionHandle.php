<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

/**
 * @template T of BaseSession
 * @extends SessionHandle<T>
 */
final class OnlineSessionHandle extends SessionHandle{

	/**
	 * @phpstan-param T $session
	 */
	public function __construct(
		private readonly BaseSession $session
	){
	}

	public function getSession() : BaseSession{
		return $this->session;
	}

	public function isDisposed() : bool{
		return false;
	}

	/**
	 * @phpstan-return \Generator<mixed, mixed, mixed, void>
	 *
	 * @safe-generator
	 */
	public function dispose() : \Generator{
		yield from [];
	}

	public function getLifecycleState() : SessionLifecycleState{
		return SessionLifecycleState::ACTIVE;
	}
}
