<?php

declare(strict_types=1);

namespace practice\settings;

use function is_string;

/**
 * One toggle: which section it belongs to, how it reads and what it defaults to.
 */
final class Setting{

	public function __construct(
		private readonly string $id,
		private readonly string $sectionId,
		private readonly string $display,
		private readonly string $description,
		private readonly bool $default
	){
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(string $id, array $data) : self{
		return new self(
			$id,
			is_string($data["section"] ?? null) ? $data["section"] : "",
			is_string($data["display"] ?? null) ? $data["display"] : $id,
			is_string($data["description"] ?? null) ? $data["description"] : "",
			($data["default"] ?? true) === true
		);
	}

	public function getId() : string{
		return $this->id;
	}

	public function getSectionId() : string{
		return $this->sectionId;
	}

	public function getDisplay() : string{
		return $this->display;
	}

	public function getDescription() : string{
		return $this->description;
	}

	public function getDefault() : bool{
		return $this->default;
	}
}
