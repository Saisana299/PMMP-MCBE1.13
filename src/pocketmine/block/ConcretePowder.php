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

namespace pocketmine\block;

use pocketmine\block\utils\ColorBlockMetaHelper;

class ConcretePowder extends Fallable{

	protected $id = self::CONCRETE_POWDER;

	public function __construct(int $meta = 0){
		$this->meta = $meta;
	}

	public function getName() : string{
		return ColorBlockMetaHelper::getColorFromMeta($this->getVariant()) . " Concrete Powder";
	}

	public function getHardness() : float{
		return 0.5;
	}

	public function getToolType() : int{
		return BlockToolType::TYPE_SHOVEL;
	}

	public function onNearbyBlockChange() : void{
		if(($block = $this->checkAdjacentWater()) !== null){
			$this->level->setBlock($this, $block);
		}else{
			parent::onNearbyBlockChange();
		}
	}

	/**
	 * @return null|Block
	 */
	public function tickFalling() : ?Block{
		return $this->checkAdjacentWater();
	}

	/**
	 * @return null|Block
	 */
	private function checkAdjacentWater() : ?Block{
		for($i = 1; $i < 6; ++$i){ //Do not check underneath
			if($this->getSide($i) instanceof Water){
				return BlockFactory::get(Block::CONCRETE, $this->meta);
			}
		}

		return null;
	}
}
