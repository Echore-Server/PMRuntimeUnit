<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use pocketmine\utils\ObjectSet;

class TestCaseFuture {

	private ObjectSet $listeners;

	public function __construct() {
		$this->listeners = new ObjectSet();
	}

	public function complete(TestCaseResult $result): void {
		foreach ($this->listeners->toArray() as $callback) {
			$callback($result);
		}
	}

	/**
	 * @return ObjectSet
	 */
	public function getListeners(): ObjectSet {
		return $this->listeners;
	}
}
