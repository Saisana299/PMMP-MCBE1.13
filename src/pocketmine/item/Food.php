<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\entity\Living;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;

abstract class Food extends Item implements FoodSource{
	public function requiresHunger() : bool{
		return true;
	}

	/**
	 * @return Item
	 */
	public function getResidue(){
		return ItemFactory::get(Item::AIR, 0, 0);
	}

	public function getAdditionalEffects() : array{
		return [];
	}

	public function onUse(Player $player, int $ticksUsed) : bool{
		$ev = new PlayerItemConsumeEvent($player, $this);
		$ev->call();

		if($ev->isCancelled() or !$player->consumeObject($this)){
			$player->getInventory()->sendContents($player);
			return false;
		}

		if($player->isSurvival()){
			$this->pop();
			$player->getInventory()->setItemInHand($this);
			$player->getInventory()->addItem($this->getResidue());
		}

		return true;
	}

	public function onConsume(Living $consumer){

	}

	public function onClickAir(Player $player, Vector3 $directionVector) : bool{
		return true;
	}
}
