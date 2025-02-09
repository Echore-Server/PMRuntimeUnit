<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

class TestCaseResult {

	public readonly int $failures;

	public function __construct(
		public readonly int   $tests,
		public readonly int   $succeeds,
		public readonly array $failureReasons
	) {
		$this->failures = count($this->failureReasons);
	}

}
