<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

class TestCaseAssertionResult {

	public readonly Constraint $constraint;

	public readonly string $message;

	public readonly array $victims;

	public readonly bool $passed;

	public function __construct(Constraint $constraint, string $message, array $victims, bool $passed) {
		$this->constraint = $constraint;
		$this->message = $message;
		$this->victims = $victims;
		$this->passed = $passed;
	}

}
