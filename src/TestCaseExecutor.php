<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use Logger;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use RuntimeException;
use SplQueue;

class TestCaseExecutor {

	/**
	 * @var SplQueue<TestCase>
	 */
	private SplQueue $cases;

	private TestCaseLogger $logger;

	private ?TestCaseParticipant $participant;

	/**
	 * @var TestCase[]
	 */
	private array $runningCases;

	private ?TaskHandler $taskHandler;

	public function __construct(Logger $parentLogger, string $prefix, ?TestCaseParticipant $participant) {
		$this->cases = new SplQueue();
		$this->logger = new TestCaseLogger($parentLogger, "PMRuntimeUnit:$prefix", $participant?->getPlayer());
		$this->participant = $participant;
		$this->runningCases = [];
		$this->taskHandler = null;
	}

	public function executeAll(Plugin $plugin): void {
		$this->taskHandler = $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use (&$tick): void {
			if (count($this->runningCases) === 0) {
				$this->taskHandler?->cancel();
				$this->taskHandler = null;

				return;
			}
			foreach ($this->runningCases as $case) {
				$case->incrementTick();
			}
		}), 1);
		$this->logger->logStart(iterator_to_array($this->cases));
		while (!$this->cases->isEmpty()) {
			$this->executeNext();
		}
	}

	protected function executeNext(): void {
		if ($this->cases->isEmpty()) {
			throw new RuntimeException("Test cases are empty");
		}

		$case = $this->cases->dequeue();

		if ($case instanceof TestCaseWithParticipant) {
			if ($this->participant === null) {
				$this->logger->logTestCaseSkip($case->getName(), "Has no participant");

				return;
			}
			$future = $case->runTestsWithParticipant($this->logger, $this->participant);
		} else {
			$future = $case->runTests($this->logger);
		}

		$this->runningCases[$case->getName()] = $case;
		$future->getListeners()->add(function() use ($case): void {
			unset($this->runningCases[$case->getName()]);
		});
	}

	public function addTestCase(TestCase $case): void {
		$this->cases->enqueue($case);
	}
}
