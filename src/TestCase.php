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

	private ?TestCaseFailureReason $failureReason;

	public function getServer(): Server {
		return Server::getInstance();
	}

	public function incrementTick(): void {
		$this->trigger($this->currentTick);
		$this->currentTick++;
	}

	/**
	 * @param int $tick
	 * @return void
	 * @internal
	 */
	public function trigger(int $tick): void {
		if (isset($this->tickTriggers[$tick])) {
			$this->failureReason = $this->invokeTestFunction($this->tickTriggers[$tick]);

			if ($this->failureReason !== null) {
				$this->tickTriggers[$tick + 1] = $this->tickTriggers[$this->currentDuration + 1]; // weird hack again
				unset($this->tickTriggers[$this->currentDuration + 1]);
			}
		}
	}

	private function invokeTestFunction(ReflectionMethod|callable $method): ?TestCaseFailureReason {
		try {
			if (is_callable($method)) {
				$method();
			} else {
				$method->invoke($this);
			}
		} catch (ReflectionException $e) {
			throw new RuntimeException();
		} catch (TestCaseFailureException $e) {
			return $e->getReason();
		} catch (Throwable $e) {
			if (isset($this->exceptionAssertions[$e::class])) {
				unset($this->exceptionAssertions[$e::class]);
				$this->pass(Constraint::EXCEPTION_THROWN, "", [$e::class], null);
			} else {
				$this->thrownExceptions[] = $e;

				return new TestCaseFailureReason("None", "Exception was thrown", null);
			}
		}

		return null;
	}

	private function pass(Constraint $constraint, string $message, array $victims, ?array $backtrace): void {
		$this->currentAssertionResults[] = new TestCaseAssertionResult(
			$constraint,
			$message,
			$victims,
			true,
			$backtrace !== null ? TestCaseAssertionResult::fetchFileAndLineFromBacktrace($backtrace) : "",
			$backtrace !== null ? TestCaseAssertionResult::fetchCodeFromBacktrace($backtrace) : "",
		);
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
				$this->cleanup();
				$future->complete(new TestCaseResult($tests, $succeeds, $failureReasons));

				return;
			}

			$method = $queue->dequeue();

			$funcName = $method->getName();
			$tests++;
			$logger->logRunTestCaseFunc($caseName, $funcName);
			$start = hrtime(true);
			$this->runTestFunction($method);

			$complete = function() use ($logger, &$tests, &$succeeds, &$failureReasons, $start, $caseName, $funcName, $next): void {
				if (empty($this->currentAssertionResults)) {
					$logger->logTestCaseFuncWarn($caseName, $funcName, "Ended with zero assertions");
				}
				$this->assertionResults[$funcName] = $this->currentAssertionResults;
				$end = hrtime(true);

				foreach ($this->thrownExceptions as $e) {
					$logger->logException($e);
				}

				if ($this->failureReason === null) {
					$logger->logTestCaseFuncPass($caseName, $funcName, $start, $end);
					$succeeds++;
				} else {
					$logger->logTestCaseFuncFail($caseName, $funcName, $start, $end, $this->failureReason);
					$failureReasons[] = $this->failureReason;
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

	public function cleanup(): void {

	}

	private function runTestFunction(ReflectionMethod $method): ?TestCaseFailureReason {
		$this->currentAssertionResults = [];
		$this->exceptionAssertions = [];
		$this->thrownExceptions = [];
		$this->currentDuration = 0;
		$this->currentTick = 0;
		$this->tickTriggers = [];
		$this->failureReason = null;

		return $this->failureReason = $this->invokeTestFunction($method);
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
		$this->assertThat($var !== null, Constraint::IS_NOT_NULL, $message, [$var], $this->getAssertionBacktrace());
	}

	private function assertThat(bool $passed, Constraint $constraint, string $message, array $victims, ?array $backtrace): void {
		if ($passed) {
			$this->pass($constraint, $message, $victims, $backtrace);
		} else {
			$this->failure($constraint, $message, $victims, $backtrace);
		}
	}

	private function failure(Constraint $constraint, string $message, array $victims, ?array $backtrace): void {
		$this->currentAssertionResults[] = $assertionResult = new TestCaseAssertionResult(
			$constraint,
			$message,
			$victims,
			false,
			$backtrace !== null ? TestCaseAssertionResult::fetchFileAndLineFromBacktrace($backtrace) : "",
			$backtrace !== null ? TestCaseAssertionResult::fetchCodeFromBacktrace($backtrace) : "",
		);

		throw new TestCaseFailureException(new TestCaseFailureReason($constraint->description(), $message, $assertionResult));
	}

	private function getAssertionBacktrace(): array {
		return debug_backtrace(limit: 2)[1];
	}

	protected function assertNull(mixed $var, string $message = ""): void {
		$this->assertThat($var === null, Constraint::IS_NULL, $message, [$var], $this->getAssertionBacktrace());
	}

	protected function assertTrue(mixed $var, string $message = ""): void {
		$this->assertThat($var === true, Constraint::IS_TRUE, $message, [$var], $this->getAssertionBacktrace());
	}

	protected function assertFalse(mixed $var, string $message = ""): void {
		$this->assertThat($var === false, Constraint::IS_FALSE, $message, [$var], $this->getAssertionBacktrace());
	}

	private function failureReturnReason(Constraint $constraint, string $message, array $victims, ?array $backtrace): TestCaseFailureReason {
		$this->currentAssertionResults[] = $assertionResult = new TestCaseAssertionResult(
			$constraint,
			$message,
			$victims,
			false,
			$backtrace !== null ? TestCaseAssertionResult::fetchFileAndLineFromBacktrace($backtrace) : "",
			$backtrace !== null ? TestCaseAssertionResult::fetchCodeFromBacktrace($backtrace) : "",
		);

		return new TestCaseFailureReason($constraint->description(), $message, $assertionResult);
	}
}
