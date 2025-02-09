<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use Closure;
use pocketmine\Server;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use SplQueue;
use Throwable;

abstract class TestCase {

	private array $assertionResults;

	private array $currentAssertionResults;

	private array $exceptionAssertions;

	/**
	 * @var Throwable[]
	 */
	private array $thrownExceptions;

	private int $currentDuration;

	/**
	 * @var array<int, Closure>
	 */
	private array $tickTriggers;

	private int $currentTick;

	public function getServer(): Server {
		return Server::getInstance();
	}

	public function incrementTick(): void {
		$this->currentTick++;
		$this->trigger($this->currentTick);
	}

	/**
	 * @param int $tick
	 * @return void
	 * @internal
	 */
	public function trigger(int $tick): void {
		if (isset($this->tickTriggers[$tick])) {
			($this->tickTriggers[$tick])();
		}
	}

	public function runTests(TestCaseLogger $logger): TestCaseFuture {
		$this->assertionResults = [];
		$caseName = $this->getName();
		$tests = 0;
		$succeeds = 0;
		$failureReasons = [];

		$this->setUp();

		$logger->logTestCaseStart($caseName);
		$caseStart = hrtime(true);
		$queue = new SplQueue();
		foreach ($this->getRunnableTestFunctions() as $method) {
			$queue->enqueue($method);
		}

		$future = new TestCaseFuture();
		$next = function() use ($queue, $caseName, $logger, &$tests, &$succeeds, &$failureReasons, &$next, $future, $caseStart): void {
			if ($queue->isEmpty()) {
				$caseEnd = hrtime(true);
				$logger->logTestCaseFinish($caseName, $caseStart, $caseEnd);
				$future->complete(new TestCaseResult($tests, $succeeds, $failureReasons));

				return;
			}

			$method = $queue->dequeue();

			$funcName = $method->getName();
			$tests++;
			$logger->logRunTestCaseFunc($caseName, $funcName);
			$start = hrtime(true);
			$failureReason = $this->runTestFunction($method);

			$complete = function() use ($logger, &$tests, &$succeeds, &$failureReasons, $failureReason, $start, $caseName, $funcName, $next): void {
				if (empty($this->currentAssertionResults)) {
					$logger->logTestCaseFuncWarn($caseName, $funcName, "Ended with zero assertions");
				}
				$this->assertionResults[$funcName] = $this->currentAssertionResults;
				$end = hrtime(true);

				foreach ($this->thrownExceptions as $e) {
					$logger->logException($e);
				}

				if ($failureReason === null) {
					$logger->logTestCaseFuncPass($caseName, $funcName, $start, $end);
					$succeeds++;
				} else {
					$logger->logTestCaseFuncFail($caseName, $funcName, $start, $end, $failureReason);
					$failureReasons[] = $failureReason;
				}

				$logger->logAllAssertionResult($this->assertionResults[$funcName] ?? []);

				$this->reset();

				$next();
			};

			if ($this->currentDuration > 0) {
				$this->on($this->currentDuration + 1, $complete);
			} else {
				$complete();
			}
		};

		$next();

		return $future;
	}

	public function getName(): string {
		return (new ReflectionClass($this))->getShortName();
	}

	public function setUp(): void {
	}

	public function getRunnableTestFunctions(): array {
		$list = [];
		$ref = new ReflectionClass($this);
		foreach ($ref->getMethods() as $method) {
			$funcName = $method->getName();
			if (str_starts_with($funcName, "test")) {
				$list[] = $method;
			}
		}

		return $list;
	}

	private function runTestFunction(ReflectionMethod $method): ?TestCaseFailureReason {
		$this->currentAssertionResults = [];
		$this->exceptionAssertions = [];
		$this->thrownExceptions = [];
		$this->currentDuration = 0;
		$this->currentTick = 0;
		$this->tickTriggers = [];

		return $this->invokeTestFunction($method);
	}

	private function invokeTestFunction(ReflectionMethod $method): ?TestCaseFailureReason {
		try {
			$method->invoke($this);
		} catch (ReflectionException $e) {
			throw new RuntimeException();
		} catch (TestCaseFailureException $e) {
			return $e->getReason();
		} catch (Throwable $e) {
			if (isset($this->exceptionAssertions[$e::class])) {
				unset($this->exceptionAssertions[$e::class]);
				$this->pass(Constraint::EXCEPTION_THROWN, "", [$e::class]);
			} else {
				$this->thrownExceptions[] = $e;

				return new TestCaseFailureReason("None", "Exception was thrown", null);
			}
		}

		return null;
	}

	private function pass(Constraint $constraint, string $message, array $victims): void {
		$this->currentAssertionResults[] = new TestCaseAssertionResult($constraint, $message, $victims, true);
	}

	public function reset(): void {

	}

	public function on(int $tick, Closure $closure): void {
		if (isset($this->tickTriggers[$tick])) {
			throw new RuntimeException("Trigger $tick is already set");
		}
		$this->tickTriggers[$tick] = $closure;
	}

	public function getCurrentDuration(): int {
		return $this->currentDuration;
	}

	public function duration(int $duration): void {
		$this->currentDuration = $duration + 1; // weird hack
	}

	protected function assertThrownExceptionNext(string $exceptionClass): void {
		$this->exceptionAssertions[$exceptionClass] = $exceptionClass;
	}

	protected function assertNotNull(mixed $var, string $message = ""): void {
		$this->assertThat($var !== null, Constraint::IS_NOT_NULL, $message, [$var]);
	}

	private function assertThat(bool $passed, Constraint $constraint, string $message, array $victims): void {
		if ($passed) {
			$this->pass($constraint, $message, $victims);
		} else {
			$this->failure($constraint, $message, $victims);
		}
	}

	private function failure(Constraint $constraint, string $message, array $victims): void {
		$this->currentAssertionResults[] = $assertionResult = new TestCaseAssertionResult($constraint, $message, $victims, false);

		throw new TestCaseFailureException(new TestCaseFailureReason($constraint->description(), $message, $assertionResult));
	}

	protected function assertNull(mixed $var, string $message = ""): void {
		$this->assertThat($var === null, Constraint::IS_NULL, $message, [$var]);
	}

	protected function assertTrue(mixed $var, string $message = ""): void {
		$this->assertThat($var === true, Constraint::IS_TRUE, $message, [$var]);
	}

	protected function assertFalse(mixed $var, string $message = ""): void {
		$this->assertThat($var === false, Constraint::IS_FALSE, $message, [$var]);
	}

	private function failureReturnReason(Constraint $constraint, string $message, array $victims): TestCaseFailureReason {
		$this->currentAssertionResults[] = $assertionResult = new TestCaseAssertionResult($constraint, $message, $victims, false);

		return new TestCaseFailureReason($constraint->description(), $message, $assertionResult);
	}
}
