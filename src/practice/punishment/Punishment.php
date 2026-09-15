<?php

declare(strict_types=1);

namespace practice\punishment;

use function is_bool;
use function is_int;
use function is_string;
use function max;
use function strtolower;

/**
 * One punishment, active or not, exactly as it was handed out.
 *
 * Records are never deleted. Lifting a ban marks it revoked and keeps who did
 * it and why; an expired ban simply stops being active. That is what makes
 * /history an audit trail rather than a list of whatever happens to be left.
 *
 * Expiry is worked out when a record is read, against the clock passed in, so
 * there is no clean-up task and no window where a lapsed ban still bites.
 */
final class Punishment{

	/** Issuer name used when the console hands something out. */
	public const CONSOLE = "CONSOLE";

	public function __construct(
		private readonly int $id,
		private readonly PunishmentType $type,
		private readonly string $targetName,
		private readonly string $issuer,
		private readonly string $reason,
		private readonly int $createdAt,
		private readonly ?int $expiresAt,
		private readonly bool $silent = false,
		private readonly ?int $revokedAt = null,
		private readonly ?string $revokedBy = null,
		private readonly ?string $revokeReason = null
	){
	}

	public static function key(string $playerName) : string{
		return strtolower($playerName);
	}

	public function getId() : int{
		return $this->id;
	}

	public function getType() : PunishmentType{
		return $this->type;
	}

	/**
	 * The lookup key: lowercase, so "Steve" and "steve" are always the same
	 * player — the mismatch that let bans come back after a restart elsewhere.
	 */
	public function getTarget() : string{
		return self::key($this->targetName);
	}

	/**
	 * The name as it was written when the punishment was handed out.
	 */
	public function getTargetName() : string{
		return $this->targetName;
	}

	public function getIssuer() : string{
		return $this->issuer;
	}

	public function getReason() : string{
		return $this->reason;
	}

	public function getCreatedAt() : int{
		return $this->createdAt;
	}

	public function getExpiresAt() : ?int{
		return $this->expiresAt;
	}

	public function isSilent() : bool{
		return $this->silent;
	}

	public function isPermanent() : bool{
		return $this->type->isLasting() && $this->expiresAt === null;
	}

	public function isRevoked() : bool{
		return $this->revokedAt !== null;
	}

	public function getRevokedAt() : ?int{
		return $this->revokedAt;
	}

	public function getRevokedBy() : ?string{
		return $this->revokedBy;
	}

	public function getRevokeReason() : ?string{
		return $this->revokeReason;
	}

	public function isExpired(int $now) : bool{
		return $this->expiresAt !== null && $now >= $this->expiresAt;
	}

	/**
	 * Still in force: a lasting kind, not lifted, not run out.
	 */
	public function isActive(int $now) : bool{
		return $this->type->isLasting() && !$this->isRevoked() && !$this->isExpired($now);
	}

	/**
	 * Seconds left, or null when it never ends.
	 */
	public function getRemaining(int $now) : ?int{
		return $this->expiresAt === null ? null : max(0, $this->expiresAt - $now);
	}

	public function withRevocation(int $revokedAt, string $revokedBy, string $reason) : self{
		return new self(
			$this->id,
			$this->type,
			$this->targetName,
			$this->issuer,
			$this->reason,
			$this->createdAt,
			$this->expiresAt,
			$this->silent,
			$revokedAt,
			$revokedBy,
			$reason
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray() : array{
		return [
			"id" => $this->id,
			"type" => $this->type->value,
			"target" => $this->targetName,
			"issuer" => $this->issuer,
			"reason" => $this->reason,
			"created" => $this->createdAt,
			"expires" => $this->expiresAt,
			"silent" => $this->silent,
			"revoked" => $this->revokedAt,
			"revoked-by" => $this->revokedBy,
			"revoke-reason" => $this->revokeReason
		];
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return self|null null for a record too damaged to trust
	 */
	public static function fromArray(array $data) : ?self{
		$type = is_string($data["type"] ?? null) ? PunishmentType::fromName($data["type"]) : null;
		if(!is_int($data["id"] ?? null) || $type === null || !is_string($data["target"] ?? null) || !is_int($data["created"] ?? null)){
			return null;
		}

		$optionalInt = static fn(string $key) : ?int => is_int($data[$key] ?? null) ? $data[$key] : null;
		$optionalString = static fn(string $key) : ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

		return new self(
			$data["id"],
			$type,
			$data["target"],
			$optionalString("issuer") ?? self::CONSOLE,
			$optionalString("reason") ?? "",
			$data["created"],
			$optionalInt("expires"),
			is_bool($data["silent"] ?? null) && $data["silent"],
			$optionalInt("revoked"),
			$optionalString("revoked-by"),
			$optionalString("revoke-reason")
		);
	}
}
