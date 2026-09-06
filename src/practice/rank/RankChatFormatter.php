<?php

declare(strict_types=1);

namespace practice\rank;

use pocketmine\player\chat\ChatFormatter;
use practice\util\Placeholder;

/**
 * Renders a chat line through the sender's rank format.
 */
final class RankChatFormatter implements ChatFormatter{

	public function __construct(private readonly string $format){
	}

	public function format(string $username, string $message) : string{
		return Placeholder::apply($this->format, [
			Rank::PLACEHOLDER_PLAYER => $username,
			Rank::PLACEHOLDER_MESSAGE => $message
		]);
	}
}
