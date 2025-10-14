<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test\DB;

use OC\DB\Snowflake\NextcloudSequenceResolver;
use OC\DB\Snowflake\SnowflakeGenerator;
use PHPUnit\Framework\Attributes\TestWith;
use Test\TestCase;

class SnowflakeTest extends TestCase {
	#[TestWith([true, true])]
	#[TestWith([true, false])]
	#[TestWith([false, true])]
	#[TestWith([true, false])]
	public function testLayout(bool $isCLIExpected, bool $is32BitsSystem): void {
		$baseTimestamp = strtotime('2025-01-01') * 1000.0;
		$resolver = $this->createMock(NextcloudSequenceResolver::class);
		$resolver->method('isAvailable')->willReturn(true);
		$resolver->method('sequence')->with($baseTimestamp + 42)->willReturn(42);

		$snowFlake = $this->getMockBuilder(SnowflakeGenerator::class)
			->setConstructorArgs([21, 22, $resolver, $isCLIExpected])
			->onlyMethods(['getCurrentMillisecond', 'is32BitsSystem'])
			->getMock();

		$snowFlake->method('getCurrentMillisecond')
			->willReturn($baseTimestamp + 42);

		$snowFlake->method('is32BitsSystem')
			->willReturn($is32BitsSystem);

		$snowFlake->setStartTimeStamp($baseTimestamp);

		$id = $snowFlake->nextId();

		[
			'sequence' => $sequence,
			'timestamp' => $timestamp,
			'workerid' => $workerId,
			'datacenter' => $datacenter,
			'iscli' => $isCLI,
		] = $snowFlake->parseId($id);

		$this->assertEquals(42, $sequence);
		$this->assertEquals(22, $workerId);
		$this->assertEquals(21, $datacenter);
		$this->assertEquals($isCLIExpected, $isCLI);
		$this->assertEquals(42, $timestamp);
	}
}
