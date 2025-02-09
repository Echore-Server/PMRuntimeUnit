<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

class TestCaseAssertionResult {

	public readonly Constraint $constraint;

	public readonly string $message;

	public readonly array $victims;

	public readonly bool $passed;

	public readonly string $fileAndLine;

	public readonly string $code;

	public function __construct(Constraint $constraint, string $message, array $victims, bool $passed, string $fileAndLine, string $code) {
		$this->constraint = $constraint;
		$this->message = $message;
		$this->victims = $victims;
		$this->passed = $passed;
		$this->fileAndLine = $fileAndLine;
		$this->code = $code;
	}

	public static function fetchFileAndLineFromBacktrace(array $backtrace): string {
		$name = basename($backtrace["file"]);

		return $name . "#L" . $backtrace["line"];
	}

	public static function fetchCodeFromBacktrace(array $backtrace): string {
		$text = $backtrace["function"] . "(";
		$args = [];
		foreach ($backtrace["args"] as $v) {
			if (is_bool($v)) {
				$args[] = $v ? "true" : "false";
				continue;
			}

			if (is_object($v)) {
				$args[] = $v::class . "@" . spl_object_id($v);
				continue;
			}

			$args[] = $v;
		}

		$text .= join(", ", $args);
		$text .= ")";

		return $text;
	}

}
