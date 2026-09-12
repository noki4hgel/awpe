<?php

declare(strict_types=1);

namespace practice\rank;

use pocketmine\utils\TextFormat;
use function is_array;
use function is_int;
use function is_string;

/**
 * One rank: how it looks in chat and above the head, and what it may do.
 *
 * Ranks are ordered by priority — a higher number outranks a lower one, which
 * is what /rank uses to stop staff from handing out something above themselves.
 */
final class Rank{

	public const PLACEHOLDER_PLAYER = "player";
	public const PLACEHOLDER_MESSAGE = "message";

	/**
	 * @param string[] $permissions
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $display,
		private readonly string $color,
		private readonly string $chatFormat,
		private readonly int $priority,
		private readonly array $permissions
	){
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(string $id, array $data) : self{
		$permissions = [];
		if(is_array($data["permissions"] ?? null)){
			foreach($data["permissions"] as $permission){
				if(is_string($permission)){
					$permissions[] = $permission;
				}
			}
		}

		$colorize = static fn(string $key, string $fallback) : string =>
			TextFormat::colorize(is_string($data[$key] ?? null) ? $data[$key] : $fallback);

		return new self(
			$id,
			$colorize("display", $id),
			$colorize("color", "&7"),
			$colorize("chat-format", "&7{player}&r: {message}"),
			is_int($data["priority"] ?? null) ? $data["priority"] : 0,
			$permissions
		);
	}

	public function getId() : string{
		return $this->id;
	}

	public function getDisplay() : string{
		return $this->display;
	}

	/**
	 * The rank's colour. It is what tints a player's name above his head — the
	 * nametag carries no prefix, so the colour alone has to identify the rank.
	 */
	public function getColor() : string{
		return $this->color;
	}

	public function getChatFormat() : string{
		return $this->chatFormat;
	}

	public function getPriority() : int{
		return $this->priority;
	}

	/**
	 * @return string[]
	 */
	public function getPermissions() : array{
		return $this->permissions;
	}

	public function outranks(self $other) : bool{
		return $this->priority > $other->priority;
	}
}
