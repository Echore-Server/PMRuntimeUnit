<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use RuntimeException;

class TestCaseFailureException extends RuntimeException {

	public function __construct(private TestCaseFailureReason $reason, string $message = "", int $code = 0, ?Throwable $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	/**
	 * @return TestCaseFailureReason
	 */
	public function getReason(): TestCaseFailureReason {
		return $this->reason;
	}
}
