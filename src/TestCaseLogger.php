<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use Logger;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use PrefixedLogger;
use ReflectionClass;

class TestCaseLogger extends PrefixedLogger {

	public function __construct(Logger $delegate, string $prefix, private readonly ?CommandSender $participant) {
		parent::__construct($delegate, $prefix);
	}

	public function log($level, $message) {
		parent::log($level, $message);
		$this->participant?->sendMessage($message);
	}


	public function logTestCaseSkip(string $caseName, string $reason): void {
		$this->info(TextFormat::GRAY . "[$caseName]" . TextFormat::DARK_AQUA . " Skipped: $reason");
	}

	public function logRunTestCaseFunc(string $caseName, string $funcName): void {
		$this->info(TextFormat::GRAY . "[$caseName:$funcName]" . TextFormat::DARK_AQUA . " Running");
	}

	public function logTestCaseFuncPass(string $caseName, string $funcName, float $startNS, float $endNS): void {
		$elapsedMS = $this->getElapsedMS($startNS, $endNS);
		$this->info(TextFormat::GRAY . "[$caseName::$funcName] " . TextFormat::GREEN . " Passed in {$elapsedMS}ms");
	}

	private function getElapsedMS(float $startNS, float $endNS): float {
		return round(($endNS / 1e+9 - $startNS / 1e+9) * 1000, 3);
	}

	public function logTestCaseFuncWarn(string $caseName, string $funcName, string $reason): void {
		$this->info(TextFormat::GRAY . "[$caseName::$funcName] " . TextFormat::YELLOW . "Warning: $reason");
	}

	public function logTestCaseFinish(string $caseName, float $startNS, float $endNS): void {
		$elapsedMS = $this->getElapsedMS($startNS, $endNS);
		$this->info(TextFormat::GRAY . "[$caseName] Finished in {$elapsedMS}ms");
	}

	public function logTestCaseStart(string $caseName): void {
		$this->info(TextFormat::GRAY . "[$caseName] Started");
	}

	/**
	 * @param TestCase[] $cases
	 * @return void
	 */
	public function logStart(array $cases): void {
		$count = count($cases);
		$this->info(TextFormat::AQUA . "Running tests ($count)");
		foreach ($cases as $case) {
			$name = (new ReflectionClass($case))->getShortName();
			$funcCount = count($case->getRunnableTestFunctions());
			$this->info(TextFormat::GRAY . "- $name: $funcCount");
		}
	}

	/**
	 * @param TestCaseAssertionResult[] $results
	 * @return void
	 */
	public function logAllAssertionResult(array $results): void {
		$count = count($results);
		$this->info(TextFormat::GOLD . "Assertions ($count):");
		foreach ($results as $result) {
			$this->logAssertionResult($result);
		}
	}

	public function logAssertionResult(TestCaseAssertionResult $result): void {
		$this->info(TextFormat::GOLD . "- {$result->constraint->name}: {$result->constraint->verbose(...$result->victims)} ($result->message): " . ($result->passed ? (TextFormat::GREEN . "PASS") : (TextFormat::RED . "FAIL")));
	}

	public function logTestCaseFuncFail(string $caseName, string $funcName, float $startNS, float $endNS, TestCaseFailureReason $reason): void {
		$elapsedMS = $this->getElapsedMS($startNS, $endNS);
		$this->warning(TextFormat::GRAY . "[$caseName::$funcName]" . TextFormat::RED . " Failed in {$elapsedMS}ms");
		$this->warning(TextFormat::RED . " - Constraint: $reason->constraintDescription");
		$this->warning(TextFormat::RED . " - $reason->message");
	}
}
