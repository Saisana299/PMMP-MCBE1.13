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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\utils\BinaryDataException;
use function count;
use function dechex;

class AvailableCommandsPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::AVAILABLE_COMMANDS_PACKET;


	/**
	 * This flag is set on all types EXCEPT the POSTFIX type. Not completely sure what this is for, but it is required
	 * for the argtype to work correctly. VALID seems as good a name as any.
	 */
	public const ARG_FLAG_VALID = 0x100000;

	/**
	 * Basic parameter types. These must be combined with the ARG_FLAG_VALID constant.
	 * ARG_FLAG_VALID | (type const)
	 */
	public const ARG_TYPE_INT             = 0x01;
	public const ARG_TYPE_FLOAT           = 0x02;
	public const ARG_TYPE_VALUE           = 0x03;
	public const ARG_TYPE_WILDCARD_INT    = 0x04;
	public const ARG_TYPE_OPERATOR        = 0x05;
	public const ARG_TYPE_TARGET          = 0x06;

	public const ARG_TYPE_FILEPATH = 0x0e;

	public const ARG_TYPE_STRING   = 0x1b;

	public const ARG_TYPE_POSITION = 0x1d;

	public const ARG_TYPE_MESSAGE  = 0x20;

	public const ARG_TYPE_RAWTEXT  = 0x22;

	public const ARG_TYPE_JSON     = 0x25;

	public const ARG_TYPE_COMMAND  = 0x2c;

	/**
	 * Enums are a little different: they are composed as follows:
	 * ARG_FLAG_ENUM | ARG_FLAG_VALID | (enum index)
	 */
	public const ARG_FLAG_ENUM = 0x200000;

	/**
	 * This is used for /xp <level: int>L. It can only be applied to integer parameters.
	 */
	public const ARG_FLAG_POSTFIX = 0x1000000;

	/**
	 * @var CommandData[]
	 * List of command data, including name, description, alias indexes and parameters.
	 */
	public $commandData = [];

	/**
	 * @var CommandEnum[]
	 * List of dynamic command enums, also referred to as "soft" enums. These can by dynamically updated mid-game
	 * without resending this packet.
	 */
	public $softEnums = [];

	protected function decodePayload(){
		/** @var string[] $enumValues */
		$enumValues = [];
		for($i = 0, $enumValuesCount = $this->getUnsignedVarInt(); $i < $enumValuesCount; ++$i){
			$enumValues[] = $this->getString();
		}

		/** @var string[] $postfixes */
		$postfixes = [];
		for($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i){
			$postfixes[] = $this->getString();
		}

		/** @var CommandEnum[] $enums */
		$enums = [];
		for($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i){
			$enums[] = $this->getEnum($enumValues);
		}

		for($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i){
			$this->commandData[] = $this->getCommandData($enums, $postfixes);
		}

		for($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i){
			$this->softEnums[] = $this->getSoftEnum();
		}
	}

	/**
	 * @param string[] $enumValueList
	 *
	 * @return CommandEnum
	 * @throws \UnexpectedValueException
	 * @throws BinaryDataException
	 */
	protected function getEnum(array $enumValueList) : CommandEnum{
		$retval = new CommandEnum();
		$retval->enumName = $this->getString();

		$listSize = count($enumValueList);

		for($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i){
			$index = $this->getEnumValueIndex($listSize);
			if(!isset($enumValueList[$index])){
				throw new \UnexpectedValueException("Invalid enum value index $index");
			}
			//Get the enum value from the initial pile of mess
			$retval->enumValues[] = $enumValueList[$index];
		}

		return $retval;
	}

	protected function getSoftEnum() : CommandEnum{
		$retval = new CommandEnum();
		$retval->enumName = $this->getString();

		for($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i){
			//Get the enum value from the initial pile of mess
			$retval->enumValues[] = $this->getString();
		}

		return $retval;
	}

	/**
	 * @param CommandEnum $enum
	 * @param int[]       $enumValueMap string enum name -> int index
	 */
	protected function putEnum(CommandEnum $enum, array $enumValueMap) : void{
		$this->putString($enum->enumName);

		$this->putUnsignedVarInt(count($enum->enumValues));
		$listSize = count($enumValueMap);
		foreach($enum->enumValues as $value){
			$index = $enumValueMap[$value] ?? -1;
			if($index === -1){
				throw new \InvalidStateException("Enum value '$value' not found");
			}
			$this->putEnumValueIndex($index, $listSize);
		}
	}

	protected function putSoftEnum(CommandEnum $enum) : void{
		$this->putString($enum->enumName);

		$this->putUnsignedVarInt(count($enum->enumValues));
		foreach($enum->enumValues as $value){
			$this->putString($value);
		}
	}

	/**
	 * @param int $valueCount
	 *
	 * @return int
	 * @throws BinaryDataException
	 */
	protected function getEnumValueIndex(int $valueCount) : int{
		if($valueCount < 256){
			return $this->getByte();
		}elseif($valueCount < 65536){
			return $this->getLShort();
		}else{
			return $this->getLInt();
		}
	}

	protected function putEnumValueIndex(int $index, int $valueCount) : void{
		if($valueCount < 256){
			$this->putByte($index);
		}elseif($valueCount < 65536){
			$this->putLShort($index);
		}else{
			$this->putLInt($index);
		}
	}

	/**
	 * @param CommandEnum[] $enums
	 * @param string[]      $postfixes
	 *
	 * @return CommandData
	 * @throws \UnexpectedValueException
	 * @throws BinaryDataException
	 */
	protected function getCommandData(array $enums, array $postfixes) : CommandData{
		$retval = new CommandData();
		$retval->commandName = $this->getString();
		$retval->commandDescription = $this->getString();
		$retval->flags = $this->getByte();
		$retval->permission = $this->getByte();
		$retval->aliases = $enums[$this->getLInt()] ?? null;

		for($overloadIndex = 0, $overloadCount = $this->getUnsignedVarInt(); $overloadIndex < $overloadCount; ++$overloadIndex){
			for($paramIndex = 0, $paramCount = $this->getUnsignedVarInt(); $paramIndex < $paramCount; ++$paramIndex){
				$parameter = new CommandParameter();
				$parameter->paramName = $this->getString();
				$parameter->paramType = $this->getLInt();
				$parameter->isOptional = $this->getBool();
				$parameter->flags = $this->getByte();

				if($parameter->paramType & self::ARG_FLAG_ENUM){
					$index = ($parameter->paramType & 0xffff);
					$parameter->enum = $enums[$index] ?? null;
					if($parameter->enum === null){
						throw new \UnexpectedValueException("deserializing $retval->commandName parameter $parameter->paramName: expected enum at $index, but got none");
					}
				}elseif($parameter->paramType & self::ARG_FLAG_POSTFIX){
					$index = ($parameter->paramType & 0xffff);
					$parameter->postfix = $postfixes[$index] ?? null;
					if($parameter->postfix === null){
						throw new \UnexpectedValueException("deserializing $retval->commandName parameter $parameter->paramName: expected postfix at $index, but got none");
					}
				}elseif(($parameter->paramType & self::ARG_FLAG_VALID) === 0){
					throw new \UnexpectedValueException("deserializing $retval->commandName parameter $parameter->paramName: Invalid parameter type 0x" . dechex($parameter->paramType));
				}

				$retval->overloads[$overloadIndex][$paramIndex] = $parameter;
			}
		}

		return $retval;
	}

	/**
	 * @param CommandData $data
	 * @param int[]       $enumIndexes string enum name -> int index
	 * @param int[]       $postfixIndexes
	 */
	protected function putCommandData(CommandData $data, array $enumIndexes, array $postfixIndexes) : void{
		$this->putString($data->commandName);
		$this->putString($data->commandDescription);
		$this->putByte($data->flags);
		$this->putByte($data->permission);

		if($data->aliases !== null){
			$this->putLInt($enumIndexes[$data->aliases->enumName] ?? -1);
		}else{
			$this->putLInt(-1);
		}

		$this->putUnsignedVarInt(count($data->overloads));
		foreach($data->overloads as $overload){
			/** @var CommandParameter[] $overload */
			$this->putUnsignedVarInt(count($overload));
			foreach($overload as $parameter){
				$this->putString($parameter->paramName);

				if($parameter->enum !== null){
					$type = self::ARG_FLAG_ENUM | self::ARG_FLAG_VALID | ($enumIndexes[$parameter->enum->enumName] ?? -1);
				}elseif($parameter->postfix !== null){
					$key = $postfixIndexes[$parameter->postfix] ?? -1;
					if($key === -1){
						throw new \InvalidStateException("Postfix '$parameter->postfix' not in postfixes array");
					}
					$type = self::ARG_FLAG_POSTFIX | $key;
				}else{
					$type = $parameter->paramType;
				}

				$this->putLInt($type);
				$this->putBool($parameter->isOptional);
				$this->putByte($parameter->flags);
			}
		}
	}

	private function argTypeToString(int $argtype, array $postfixes) : string{
		if($argtype & self::ARG_FLAG_VALID){
			if($argtype & self::ARG_FLAG_ENUM){
				return "stringenum (" . ($argtype & 0xffff) . ")";
			}

			switch($argtype & 0xffff){
				case self::ARG_TYPE_INT:
					return "int";
				case self::ARG_TYPE_FLOAT:
					return "float";
				case self::ARG_TYPE_VALUE:
					return "mixed";
				case self::ARG_TYPE_TARGET:
					return "target";
				case self::ARG_TYPE_STRING:
					return "string";
				case self::ARG_TYPE_POSITION:
					return "xyz";
				case self::ARG_TYPE_MESSAGE:
					return "message";
				case self::ARG_TYPE_RAWTEXT:
					return "text";
				case self::ARG_TYPE_JSON:
					return "json";
				case self::ARG_TYPE_COMMAND:
					return "command";
			}
		}elseif($argtype & self::ARG_FLAG_POSTFIX){
			$postfix = $postfixes[$argtype & 0xffff];

			return "int (postfix $postfix)";
		}else{
			throw new \UnexpectedValueException("Unknown arg type 0x" . dechex($argtype));
		}

		return "unknown ($argtype)";
	}

	protected function encodePayload(){
		/** @var int[] $enumValueIndexes */
		$enumValueIndexes = [];
		/** @var int[] $postfixIndexes */
		$postfixIndexes = [];
		/** @var int[] $enumIndexes */
		$enumIndexes = [];
		/** @var CommandEnum[] $enums */
		$enums = [];
		foreach($this->commandData as $commandData){
			if($commandData->aliases !== null){
				if(!isset($enumIndexes[$commandData->aliases->enumName])){
					$enums[$enumIndexes[$commandData->aliases->enumName] = count($enumIndexes)] = $commandData->aliases;
				}

				foreach($commandData->aliases->enumValues as $str){
					$enumValueIndexes[$str] = $enumValueIndexes[$str] ?? count($enumValueIndexes); //latest index
				}
			}

			foreach($commandData->overloads as $overload){
				/**
				 * @var CommandParameter[] $overload
				 * @var CommandParameter   $parameter
				 */
				foreach($overload as $parameter){
					if($parameter->enum !== null){
						if(!isset($enumIndexes[$parameter->enum->enumName])){
							$enums[$enumIndexes[$parameter->enum->enumName] = count($enumIndexes)] = $parameter->enum;
						}
						foreach($parameter->enum->enumValues as $str){
							$enumValueIndexes[$str] = $enumValueIndexes[$str] ?? count($enumValueIndexes);
						}
					}

					if($parameter->postfix !== null){
						$postfixIndexes[$parameter->postfix] = $postfixIndexes[$parameter->postfix] ?? count($postfixIndexes);
					}
				}
			}
		}

		$this->putUnsignedVarInt(count($enumValueIndexes));
		foreach($enumValueIndexes as $enumValue => $index){
			$this->putString((string) $enumValue); //stupid PHP key casting D:
		}

		$this->putUnsignedVarInt(count($postfixIndexes));
		foreach($postfixIndexes as $postfix => $index){
			$this->putString((string) $postfix); //stupid PHP key casting D:
		}

		$this->putUnsignedVarInt(count($enums));
		foreach($enums as $enum){
			$this->putEnum($enum, $enumValueIndexes);
		}

		$this->putUnsignedVarInt(count($this->commandData));
		foreach($this->commandData as $data){
			$this->putCommandData($data, $enumIndexes, $postfixIndexes);
		}

		$this->putUnsignedVarInt(count($this->softEnums));
		foreach($this->softEnums as $enum){
			$this->putSoftEnum($enum);
		}
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleAvailableCommands($this);
	}
}
