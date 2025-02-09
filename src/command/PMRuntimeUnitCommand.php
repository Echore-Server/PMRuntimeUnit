<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit\command;

use Echore\PMRuntimeUnit\PMRuntimeUnit;
use Echore\PMRuntimeUnit\TestCaseExecutor;
use Echore\PMRuntimeUnit\TestCaseParticipant;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;

class PMRuntimeUnitCommand extends Command implements PluginOwned {

	public function __construct(private Plugin $plugin, string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = []) {
		parent::__construct($name, $description, $usageMessage, $aliases);
		$this->setPermission(DefaultPermissions::ROOT_OPERATOR);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if ($sender instanceof Player) {
			$participant = new TestCaseParticipant([$sender]);
		} else {
			$participant = null;
		}

		$executor = new TestCaseExecutor(Server::getInstance()->getLogger(), "{$sender->getName()}", $participant);

		foreach (PMRuntimeUnit::getTestCases() as $case) {
			$executor->addTestCase($case);
		}

		$executor->executeAll($this->plugin);
	}

	public function getOwningPlugin(): Plugin {
		return $this->plugin;
	}
}
