<?php

declare(strict_types=1);

namespace Echore\PMRuntimeUnit;

use RuntimeException;

class TestCaseWithParticipant extends TestCase {

	private ?TestCaseParticipant $participant = null;

	public function runTestsWithParticipant(TestCaseLogger $logger, TestCaseParticipant $participant): TestCaseFuture {
		$this->participant = $participant;

		$this->setUpWithParticipant($participant);

		return parent::runTests($logger);
	}

	public function setUpWithParticipant(TestCaseParticipant $participant): void {

	}

	public function getParticipant(): TestCaseParticipant {
		return $this->participant ?? throw new RuntimeException("Test case didn't started with participant");
	}
}
