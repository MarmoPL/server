<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OC;

use OCP\ISnowflakeId;
use Override;

/**
 * Nextcloud Snowflake ID
 *
 * Get information about Snowflake Id
 *
 * @since 33.0.0
 */
final class SnowflakeId implements ISnowflakeId {
	private int $seconds = 0;
	private int $milliseconds = 0;
	private bool $isCli = false;
	/** @var int<0, 511> */
	private int $serverId = 0;
	/** @var int<0, 4095> */
	private int $sequenceId = 0;

	public function __construct(
		private readonly int|float $id,
	) {
	}

	private function decode(): void {
		if ($this->seconds !== 0) {
			return;
		}

		if (PHP_INT_SIZE === 8) {
			// 64 bits handling
			$id = (int)$this->id;
			$firstHalf = $id >> 32;
			$secondHalf = $id & 0xFFFFFFFF;
		} else {
			// 32 bits handling
			$id = hex2bin(sprintf('%016x', $this->id));
			assert($id !== false);
			$id = unpack('Nfirst/Nsecond', $id);
			assert($id !== false);
			$firstHalf = $id['first'];
			$secondHalf = $id['second'];
		}

		// First half without first bit is seconds
		$this->seconds = $firstHalf & 0x7FFFFFFF;

		// Decode second half
		$this->milliseconds = $secondHalf >> 22;
		$this->serverId = ($secondHalf >> 13) & 0x1FF;
		$this->isCli = (bool)(($secondHalf >> 12) & 0x1);
		$this->sequenceId = $secondHalf & 0xFFF;
	}

	#[Override]
	public function isCli(): bool {
		return $this->isCli;
	}

	#[Override]
	public function numeric(): int|float {
		return $this->id;
	}

	#[Override]
	public function seconds(): int {
		$this->decode();
		return $this->seconds;
	}

	#[Override]
	public function milliseconds(): int {
		$this->decode();
		return $this->milliseconds;
	}

	#[Override]
	public function createdAt(): float {
		$this->decode();
		return $this->seconds + self::TS_OFFSET + ($this->milliseconds / 1000);
	}

	#[Override]
	public function serverId(): int {
		$this->decode();
		return	$this->serverId;
	}

	#[Override]
	public function sequenceId(): int {
		$this->decode();
		return $this->sequenceId;
	}
}
