<?php

declare(strict_types=1);

namespace practice\duel\match;

/**
 * The life of a match, in order. A match only ever moves forward.
 */
enum MatchState{

	/** The arena copy is being made; players are still in the lobby. */
	case PREPARING;

	/** Players are in the arena, frozen, counting down. */
	case COUNTDOWN;

	/** The fight is on. */
	case FIGHTING;

	/** Someone won (or it was a draw); a short pause before everyone leaves. */
	case ENDING;

	/** Over and cleaned up. */
	case CLOSED;
}
