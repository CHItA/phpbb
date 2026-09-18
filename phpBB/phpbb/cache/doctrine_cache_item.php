<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

class doctrine_cache_item implements CacheItemInterface
{
	private string $key;
	private mixed $value;
	private bool $hit;
	private ?DateTimeInterface $expiration = null;

	public function __construct(string $key, mixed $value, bool $hit)
	{
		$this->key = $key;
		$this->value = $value;
		$this->hit = $hit;
	}

	public function getKey(): string
	{
		return $this->key;
	}

	public function get(): mixed
	{
		return $this->value;
	}

	public function isHit(): bool
	{
		return $this->hit;
	}

	public function set(mixed $value): static
	{
		$this->value = $value;
		return $this;
	}

	public function expiresAt(?DateTimeInterface $expiration): static
	{
		$this->expiration = $expiration;
		return $this;
	}

	public function expiresAfter(DateInterval|int|null $time): static
	{
		if ($time === null)
		{
			$this->expiration = null;
		}
		else if ($time instanceof DateInterval)
		{
			$this->expiration = (new DateTimeImmutable())->add($time);
		}
		else
		{
			$this->expiration = (new DateTimeImmutable())->modify('+' . $time . ' seconds');
		}

		return $this;
	}

	public function get_ttl(): int
	{
		if ($this->expiration === null)
		{
			return 0;
		}

		return max(0, $this->expiration->getTimestamp() - time());
	}
}
