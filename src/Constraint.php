<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

enum Constraint {
	case IS_TRUE;
	case IS_FALSE;
	case IS_NULL;
	case IS_NOT_NULL;
	case EXCEPTION_THROWN;

	public function description(): string {
		return match ($this) {
			self::IS_TRUE => "var is TRUE",
			self::IS_FALSE => "var is FALSE",
			self::IS_NULL => "var is NULL",
			self::IS_NOT_NULL => "var is NOT NULL",
			self::EXCEPTION_THROWN => "exception was thrown"
		};
	}

	public function verbose(mixed ...$values): string {
		$formattedValues = [];
		foreach ($values as $v) {
			if (is_bool($v)) {
				$formattedValues[] = $v ? "true" : "false";
				continue;
			}

			$formattedValues[] = $v;
		}

		return sprintf(
			match ($this) {
				self::IS_TRUE => "%s is TRUE",
				self::IS_FALSE => "%s is FALSE",
				self::IS_NULL => "%s is NULL",
				self::IS_NOT_NULL => "%s is NOT NULL",
				self::EXCEPTION_THROWN => "%s was thrown"
			}, ...$formattedValues
		);
	}
}
