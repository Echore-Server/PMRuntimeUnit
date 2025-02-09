<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

class PMRuntimeUnit {

	private static array $cases = [];

	/**
	 * @return TestCase[]
	 */
	public static function getTestCases(): array {
		return self::$cases;
	}

	public static function addTestCase(TestCase $testCase): void {
		self::$cases[] = $testCase;
	}
}
