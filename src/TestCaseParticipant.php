<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use pocketmine\player\Player;
use RuntimeException;

class TestCaseParticipant {

	public function __construct(
		private array $players
	) {
		if (empty($this->players)) {
			throw new RuntimeException("Players is empty");
		}
	}

	public function getPlayer(): Player {
		return $this->players[array_key_first($this->players)];
	}

}
