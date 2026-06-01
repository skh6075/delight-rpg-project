<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

enum SessionLifecycleState{
	case ACTIVE;
	case PROMOTED;
	case DISPOSED;
}
