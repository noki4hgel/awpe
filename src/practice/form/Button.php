<?php

declare(strict_types=1);

namespace practice\form;

use function str_starts_with;

/**
 * One entry of a SimpleForm. The image is optional; when a path looks like a
 * URL it is sent as such, otherwise as a resource pack texture path.
 */
final class Button implements \JsonSerializable{

	public const IMAGE_PATH = "path";
	public const IMAGE_URL = "url";

	public function __construct(
		private readonly string $text,
		private readonly ?string $image = null
	){
	}

	public function getText() : string{
		return $this->text;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize() : array{
		$data = ["text" => $this->text];

		if($this->image !== null && $this->image !== ""){
			$data["image"] = [
				"type" => str_starts_with($this->image, "http") ? self::IMAGE_URL : self::IMAGE_PATH,
				"data" => $this->image
			];
		}

		return $data;
	}
}
