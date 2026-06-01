<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

/**
 * @template T of BaseSession
 */
abstract class SessionHandle{

	/**
	 * @phpstan-return T
	 */
	abstract public function getSession() : BaseSession;

	abstract public function isDisposed() : bool;

	/**
	 * @phpstan-return \Generator<mixed, mixed, mixed, void>
	 */
	abstract public function dispose() : \Generator;

	abstract public function getLifecycleState() : SessionLifecycleState;
}
