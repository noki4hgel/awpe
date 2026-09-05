<?php

declare(strict_types=1);

namespace practice\util;

use pocketmine\network\mcpe\protocol\types\DeviceOS;

/**
 * Maps the raw DeviceOS value sent during login onto the configuration key used
 * by nametag.devices, so display names stay fully configurable.
 */
final class DeviceUtil{

	public const UNKNOWN_KEY = "unknown";
	public const LINUX_KEY = "linux";

	private function __construct(){
		//NOOP
	}

	/**
	 * Android clients that report an empty device model are desktop Linux builds.
	 */
	public static function keyOf(int $deviceOs, string $deviceModel) : string{
		if($deviceOs === DeviceOS::ANDROID && $deviceModel === ""){
			return self::LINUX_KEY;
		}

		return match($deviceOs){
			DeviceOS::ANDROID => "android",
			DeviceOS::IOS => "ios",
			DeviceOS::OSX => "osx",
			DeviceOS::AMAZON => "amazon",
			DeviceOS::GEAR_VR => "gear_vr",
			DeviceOS::HOLOLENS => "hololens",
			DeviceOS::WINDOWS_10 => "windows_10",
			DeviceOS::WIN32 => "win32",
			DeviceOS::DEDICATED => "dedicated",
			DeviceOS::TVOS => "tvos",
			DeviceOS::PLAYSTATION => "playstation",
			DeviceOS::NINTENDO => "nintendo",
			DeviceOS::XBOX => "xbox",
			DeviceOS::WINDOWS_PHONE => "windows_phone",
			default => self::UNKNOWN_KEY,
		};
	}
}
