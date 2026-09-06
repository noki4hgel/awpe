<?php

declare(strict_types=1);

namespace practice\settings;

use function is_string;

/**
 * A tab of the settings menu. Sections are declared in config.yml, so adding
 * one is a config edit plus tagging settings with its id.
 */
final class SettingSection{

	public function __construct(
		private readonly string $id,
		private readonly string $display,
		private readonly string $icon
	){
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(string $id, array $data) : self{
		return new self(
			$id,
			is_string($data["display"] ?? null) ? $data["display"] : $id,
			is_string($data["icon"] ?? null) ? $data["icon"] : ""
		);
	}

	public function getId() : string{
		return $this->id;
	}

	public function getDisplay() : string{
		return $this->display;
	}

	public function getIcon() : string{
		return $this->icon;
	}
}
