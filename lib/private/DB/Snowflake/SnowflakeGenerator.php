<?php

declare(strict_types=1);

/**
 * This file is based on the package: godruoyi/php-snowflake.
 * SPDX-FileCopyrightText: 2024 Godruoyi <g@godruoyi.com>
 * SPDX-License-Identifier: MIT
 */

namespace OC\DB\Snowflake;

use OCP\Util;

class SnowflakeGenerator {
	public const MAX_TIMESTAMP_LENGTH = 42;
	public const MAX_DATACENTER_LENGTH = 5;
	public const MAX_WORKID_LENGTH = 5;
	public const MAX_CLI_LENGTH = 1;
	public const MAX_SEQUENCE_LENGTH = 10;
	public const MAX_SEQUENCE_SIZE = (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH));

	/**
	 * The data center id.
	 */
	protected int $datacenter;

	/**
	 * The worker id.
	 */
	protected int $workerId;

	/**
	 * The start timestamp.
	 */
	protected ?float $startTime = null;

	/**
	 * The last timestamp for the random generator.
	 */
	protected float $lastTimeStamp = -1;

	/**
	 * The sequence number for the random generator.
	 */
	protected int $sequence = 0;

	/**
	 * Build Snowflake Instance.
	 */
	public function __construct(
		int $datacenter,
		int $workerId,
		private readonly NextcloudSequenceResolver $sequenceResolver,
		private readonly bool $isCLI,
	) {
		$maxDataCenter = -1 ^ (-1 << self::MAX_DATACENTER_LENGTH);
		$maxWorkId = -1 ^ (-1 << self::MAX_WORKID_LENGTH);

		// If not set datacenterID or workID, we will set a default value to use.
		$this->datacenter = $datacenter < 0 || $datacenter > $maxDataCenter ? random_int(0, 31) : $datacenter;
		$this->workerId = $workerId < 0 || $workerId > $maxWorkId ? random_int(0, 31) : $workerId;
	}

	/**
	 * Alternative to decbin which takes an int. This ignores the fractional part.
	 */
	private static function floatbin(float $number): string {
		if (!function_exists('bccomp') || !function_exists('bcmod') || !function_exists('bcdiv')) {
			throw new \RuntimeException('bcmath is a required extension on 32bits system.');
		}

		// Split number into integer and fractional parts
		$parts = explode('.', (string)$number);
		if (count($parts) < 1) {
			throw new \RuntimeException('Unable to convert float to a string');
		}

		$integerPart = $parts[0];

		// Convert integer part (use BCMath for big numbers)
		$intBinary = '';
		$int = $integerPart;
		while (bccomp($int, '0') > 0) {
			$rem = bcmod($int, '2');
			$intBinary = $rem . $intBinary;
			$int = bcdiv($int, '2', 0);
		}

		if ($intBinary === '') {
			$intBinary = '0';
		}
		return $intBinary;
	}

	/**
	 * @internal For unit tests only.
	 */
	public function is32BitsSystem(): bool {
		return 2147483647 === PHP_INT_MAX;
	}

	/**
	 * Get snowflake id.
	 */
	public function nextId(): int|float {
		$currentTime = $this->getCurrentMillisecond();
		while (($sequence = $this->callResolver($currentTime)) > self::MAX_SEQUENCE_SIZE) {
			usleep(1);
			$currentTime = $this->getCurrentMillisecond();
		}

		$timestamp = $this->getStartTimeStamp();

		if ($this->is32BitsSystem()) {
			// Slow version for 32-bits machine using string concatenation
			$stringResult = str_pad(self::floatbin($currentTime - $timestamp), self::MAX_TIMESTAMP_LENGTH, '0', STR_PAD_LEFT)
				. str_pad(decbin($this->datacenter), self::MAX_DATACENTER_LENGTH, '0', STR_PAD_LEFT)
				. str_pad(decbin($this->workerId), self::MAX_WORKID_LENGTH, '0', STR_PAD_LEFT)
				. ($this->isCLI ? '1' : '0')
				. str_pad(decbin($sequence), self::MAX_SEQUENCE_LENGTH, '0', STR_PAD_LEFT);
			return Util::numericToNumber(bindec($stringResult));
		} else {
			// Faster version for 64-bits machine using bits-shifts
			$isCLILeftMoveLength = self::MAX_SEQUENCE_LENGTH;
			$workerLeftMoveLength = self::MAX_CLI_LENGTH + $isCLILeftMoveLength;
			$datacenterLeftMoveLength = self::MAX_WORKID_LENGTH + $workerLeftMoveLength;
			$timestampLeftMoveLength = self::MAX_DATACENTER_LENGTH + $datacenterLeftMoveLength;

			return (($currentTime - $timestamp) << $timestampLeftMoveLength)
				| ($this->datacenter << $datacenterLeftMoveLength)
				| ($this->workerId << $workerLeftMoveLength)
				| ((int)$this->isCLI << $isCLILeftMoveLength)
				| ($sequence);
		}
	}

	/**
	 * @return array{
	 *      timestamp: int|float,
	 *      sequence: int|float,
	 *      workerid: int|float,
	 *      iscli: bool,
	 *      datacenter: int|float,
	 *  }
	 */
	public static function parseId(int|float $id): array {
		$id = self::floatbin($id);

		return[
			'timestamp' => bindec(substr($id, 0, -21)),
			'datacenter' => bindec(substr($id, -21, 5)),
			'workerid' => bindec(substr($id, -16, 5)),
			'iscli' => substr($id, -11, 1) === '1',
			'sequence' => bindec(substr($id, -10)),
		];
	}

	/**
	 * Get current millisecond time.
	 */
	public function getCurrentMillisecond(): float {
		return microtime(true) * 1000;
	}

	/**
	 * Set start time (millisecond).
	 * @throw \InvalidArgumentException
	 */
	public function setStartTimeStamp(float $millisecond): self {
		$missTime = $this->getCurrentMillisecond() - $millisecond;

		if ($missTime < 0) {
			throw new \InvalidArgumentException('The start time cannot be greater than the current time');
		}

		$maxTimeDiff = -1 ^ (-1 << self::MAX_TIMESTAMP_LENGTH);

		if ($missTime > $maxTimeDiff) {
			throw new \InvalidArgumentException(sprintf('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << %d), You can reset the start time to fix this', self::MAX_TIMESTAMP_LENGTH));
		}

		$this->startTime = $millisecond;

		return $this;
	}

	/**
	 * Get start timestamp (millisecond), If not set default to 2019-08-08 08:08:08.
	 */
	public function getStartTimeStamp(): float|int {
		if (! is_null($this->startTime)) {
			return $this->startTime;
		}

		// We set a default start time if you not set.
		return strtotime('2025-01-01') * 1000;
	}

	/**
	 * Call resolver.
	 */
	protected function callResolver(float $currentTime): int {
		// Memcache based resolver
		if ($this->sequenceResolver->isAvailable()) {
			return $this->sequenceResolver->sequence($currentTime);
		}

		// random fallback
		if ($this->lastTimeStamp === $currentTime) {
			$this->sequence++;
			$this->lastTimeStamp = $currentTime;

			return $this->sequence;
		}

		$this->sequence = crc32(uniqid((string)random_int(0, PHP_INT_MAX), true)) % self::MAX_SEQUENCE_SIZE;
		$this->lastTimeStamp = $currentTime;

		return $this->sequence;
	}
}
