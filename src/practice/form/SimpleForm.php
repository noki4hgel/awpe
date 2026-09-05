<?php

declare(strict_types=1);

namespace practice\form;

use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use function count;
use function gettype;
use function is_int;

/**
 * Minimal button-menu form. PocketMine only ships the Form interface, so the
 * core carries its own implementation instead of pulling in a form library.
 *
 * The callback receives the index of the pressed button, or null when the
 * player closed the form.
 */
final class SimpleForm implements Form{

	/** @var Button[] */
	private array $buttons = [];

	/** @phpstan-var \Closure(Player, int|null) : void */
	private \Closure $onSubmit;

	public function __construct(
		private string $title = "",
		private string $content = "",
		?\Closure $onSubmit = null
	){
		$this->onSubmit = $onSubmit ?? static function(Player $player, ?int $selected) : void{
			//NOOP
		};
	}

	public function setTitle(string $title) : self{
		$this->title = $title;
		return $this;
	}

	public function setContent(string $content) : self{
		$this->content = $content;
		return $this;
	}

	/**
	 * @phpstan-param \Closure(Player, int|null) : void $onSubmit
	 */
	public function setHandler(\Closure $onSubmit) : self{
		$this->onSubmit = $onSubmit;
		return $this;
	}

	public function addButton(string $text, ?string $image = null) : self{
		$this->buttons[] = new Button($text, $image);
		return $this;
	}

	public function getButtonCount() : int{
		return count($this->buttons);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize() : array{
		return [
			"type" => "form",
			"title" => $this->title,
			"content" => $this->content,
			"buttons" => $this->buttons
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if($data === null){
			($this->onSubmit)($player, null);
			return;
		}

		if(!is_int($data)){
			throw new FormValidationException("Expected int or null, got " . gettype($data));
		}
		if(!isset($this->buttons[$data])){
			throw new FormValidationException("Button $data does not exist");
		}

		($this->onSubmit)($player, $data);
	}
}
