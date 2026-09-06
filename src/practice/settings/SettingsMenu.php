<?php

declare(strict_types=1);

namespace practice\settings;

use pocketmine\player\Player;
use practice\form\CustomForm;
use practice\form\SimpleForm;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;
use practice\util\Placeholder;
use function array_values;
use function count;

/**
 * The two-step settings UI: pick a section, then flip its toggles.
 *
 * Both forms are built from the registry, so a setting added to config.yml
 * appears here without any change to this class.
 */
final class SettingsMenu{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly SettingRegistry $registry,
		private readonly SessionManager $sessions
	){
	}

	public function sendSectionMenu(Session $session) : void{
		$sections = array_values($this->registry->getSections());
		if(count($sections) === 0){
			$session->getPlayer()->sendMessage($this->config->getMessage("settings-empty"));
			return;
		}

		$form = new SimpleForm(
			$this->config->getString("settings.form.title", "§l§bSettings"),
			$this->config->getString("settings.form.content")
		);

		foreach($sections as $section){
			$form->addButton($section->getDisplay(), $section->getIcon());
		}

		$form->setHandler(function(Player $player, ?int $selected) use ($sections) : void{
			if($selected === null || !isset($sections[$selected])){
				return;
			}

			$session = $this->sessions->get($player);
			if($session !== null){
				$this->sendSectionForm($session, $sections[$selected]);
			}
		});

		$session->getPlayer()->sendForm($form);
	}

	public function sendSectionForm(Session $session, SettingSection $section) : void{
		$settings = $this->registry->ofSection($section->getId());
		if(count($settings) === 0){
			$session->getPlayer()->sendMessage($this->config->getMessage("settings-empty"));
			return;
		}

		$form = new CustomForm(Placeholder::apply(
			$this->config->getString("settings.form.section-title", "§l{section}"),
			["section" => $section->getDisplay()]
		));

		$labelTemplate = $this->config->getString("settings.form.toggle", "{display}\n§7{description}");
		foreach($settings as $id => $setting){
			$form->addToggle(
				$id,
				Placeholder::apply($labelTemplate, [
					"display" => $setting->getDisplay(),
					"description" => $setting->getDescription()
				]),
				$this->registry->isEnabled($session, $id)
			);
		}

		$form->setHandler(function(Player $player, ?array $values) : void{
			if($values === null){
				return;
			}

			$session = $this->sessions->get($player);
			if($session === null){
				return;
			}

			foreach($values as $id => $value){
				$this->registry->set($session, $id, $value);
			}
			//Written immediately: a toggle the player flipped should survive a crash.
			$session->save();

			$player->sendMessage($this->config->getMessage("settings-saved"));
		});

		$session->getPlayer()->sendForm($form);
	}
}
