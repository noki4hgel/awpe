<?php

declare(strict_types=1);

namespace practice\session;

/**
 * Where a player currently is. Every manager decides what it may do with a
 * player by asking for this instead of guessing from the world he stands in.
 */
enum SessionState{
	case LOBBY;
	case FFA;
	case DUEL;
}
