<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

class TestCaseFailureReason {

	public function __construct(
		public readonly string                   $constraintDescription,
		public readonly string                   $message,
		public readonly ?TestCaseAssertionResult $assertionResult
	) {
	}

}
