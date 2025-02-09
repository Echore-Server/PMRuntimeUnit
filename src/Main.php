<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use Echore\PMRuntimeUnit\command\PMRuntimeUnitCommand;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase {

	protected function onLoad(): void {
		$this->getServer()->getCommandMap()->registerAll("pmruntimeunit", [
			new PMRuntimeUnitCommand($this, "pmruntimeunit", "Executes PMRuntimeUnit Test Cases", null, ["runtests"])
		]);
	}

}
