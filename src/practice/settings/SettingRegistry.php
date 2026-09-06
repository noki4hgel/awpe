<?php

declare(strict_types=1);

namespace practice\settings;

use practice\session\Session;
use function is_array;
use function is_string;

/**
 * The settings a player can toggle, grouped into sections.
 *
 * Reading a value always goes through here rather than through PlayerData
 * directly, so an unset value falls back to the setting's configured default
 * instead of to a bare false.
 */
final class SettingRegistry{

	/** @var array<string, SettingSection> */
	private array $sections = [];

	/** @var array<string, Setting> */
	private array $settings = [];

	/**
	 * @param array<string, mixed> $sections
	 * @param array<string, mixed> $entries
	 *
	 * @return string[] ids that were skipped
	 */
	public function loadFromConfig(array $sections, array $entries) : array{
		$this->sections = [];
		$this->settings = [];
		$failed = [];

		foreach($sections as $id => $data){
			if(!is_string($id) || !is_array($data)){
				$failed[] = (string) $id;
				continue;
			}
			$this->sections[$id] = SettingSection::fromConfig($id, $data);
		}

		foreach($entries as $id => $data){
			if(!is_string($id) || !is_array($data)){
				$failed[] = (string) $id;
				continue;
			}

			$setting = Setting::fromConfig($id, $data);
			if(!isset($this->sections[$setting->getSectionId()])){
				//A setting nobody can reach is worse than a missing one.
				$failed[] = $id;
				continue;
			}

			$this->settings[$id] = $setting;
		}

		return $failed;
	}

	/**
	 * @return array<string, SettingSection>
	 */
	public function getSections() : array{
		return $this->sections;
	}

	public function getSection(string $id) : ?SettingSection{
		return $this->sections[$id] ?? null;
	}

	public function get(string $id) : ?Setting{
		return $this->settings[$id] ?? null;
	}

	/**
	 * @return array<string, Setting>
	 */
	public function all() : array{
		return $this->settings;
	}

	/**
	 * @return array<string, Setting>
	 */
	public function ofSection(string $sectionId) : array{
		$result = [];
		foreach($this->settings as $id => $setting){
			if($setting->getSectionId() === $sectionId){
				$result[$id] = $setting;
			}
		}

		return $result;
	}

	/**
	 * Whether a setting is on for a player. Unknown ids read as enabled, so a
	 * feature keeps working if its config entry is ever removed.
	 */
	public function isEnabled(Session $session, string $id) : bool{
		$setting = $this->get($id);
		if($setting === null){
			return true;
		}

		return $session->getData()->getSetting($id, $setting->getDefault());
	}

	public function set(Session $session, string $id, bool $value) : void{
		$session->getData()->setSetting($id, $value);
	}
}
