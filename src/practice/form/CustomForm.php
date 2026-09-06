<?php

declare(strict_types=1);

namespace practice\form;

use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use function array_values;
use function array_filter;
use function count;
use function gettype;
use function is_array;
use function is_bool;

/**
 * Custom form carrying labels and toggles.
 *
 * Only the element types the core actually needs are implemented; the response
 * is handed back as an ordered list of the toggle values, keyed by the ids the
 * caller supplied, so a handler never has to count array positions.
 */
final class CustomForm implements Form{

	private const TYPE_LABEL = "label";
	private const TYPE_TOGGLE = "toggle";

	/** @var array<int, array<string, mixed>> */
	private array $elements = [];

	/** @var array<int, string|null> element index => toggle id, null for non-inputs */
	private array $ids = [];

	/** @phpstan-var \Closure(Player, array<string, bool>|null) : void */
	private \Closure $onSubmit;

	public function __construct(
		private string $title = "",
		?\Closure $onSubmit = null
	){
		$this->onSubmit = $onSubmit ?? static function(Player $player, ?array $values) : void{
			//NOOP
		};
	}

	public function setTitle(string $title) : self{
		$this->title = $title;
		return $this;
	}

	/**
	 * @phpstan-param \Closure(Player, array<string, bool>|null) : void $onSubmit
	 */
	public function setHandler(\Closure $onSubmit) : self{
		$this->onSubmit = $onSubmit;
		return $this;
	}

	public function addLabel(string $text) : self{
		$this->elements[] = ["type" => self::TYPE_LABEL, "text" => $text];
		$this->ids[] = null;
		return $this;
	}

	public function addToggle(string $id, string $text, bool $default) : self{
		$this->elements[] = ["type" => self::TYPE_TOGGLE, "text" => $text, "default" => $default];
		$this->ids[] = $id;
		return $this;
	}

	public function getToggleCount() : int{
		return count(array_filter($this->ids, static fn(?string $id) => $id !== null));
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize() : array{
		return [
			"type" => "custom_form",
			"title" => $this->title,
			"content" => array_values($this->elements)
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if($data === null){
			($this->onSubmit)($player, null);
			return;
		}

		if(!is_array($data)){
			throw new FormValidationException("Expected array or null, got " . gettype($data));
		}
		if(count($data) !== count($this->elements)){
			throw new FormValidationException("Expected " . count($this->elements) . " values, got " . count($data));
		}

		$values = [];
		foreach(array_values($data) as $index => $value){
			$id = $this->ids[$index] ?? null;
			if($id === null){
				//Labels come back as null and carry no answer.
				continue;
			}
			if(!is_bool($value)){
				throw new FormValidationException("Expected bool for toggle \"$id\", got " . gettype($value));
			}
			$values[$id] = $value;
		}

		($this->onSubmit)($player, $values);
	}
}
