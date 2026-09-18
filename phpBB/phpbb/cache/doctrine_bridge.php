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

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * This class is a bridge between Doctrine's cache implementation and phpBB's cache drivers.
 */
class doctrine_bridge implements CacheItemPoolInterface
{
	/**
	 * @var \phpbb\cache\driver\driver_interface
	 */
	private $cache;

	/** @var array<string, doctrine_cache_item> */
	private $deferred = [];

	/**
	 * Constructor.
	 *
	 * @param \phpbb\cache\driver\driver_interface $cache The cache driver
	 */
	public function __construct(\phpbb\cache\driver\driver_interface $cache)
	{
		$this->cache = $cache;
	}

	public function getItem(string $key): CacheItemInterface
	{
		$value = $this->cache->sql_load($key);
		return new doctrine_cache_item($key, $value, $value !== false);
	}

	public function getItems(array $keys = []): iterable
	{
		foreach ($keys as $key)
		{
			yield $key => $this->getItem($key);
		}
	}

	public function hasItem(string $key): bool
	{
		return $this->cache->_exists($this->cache->get_cache_id_from_sql_query($key));
	}

	public function clear(): bool
	{
		$this->cache->purge();
		return true;
	}

	public function deleteItem(string $key): bool
	{
		$this->cache->destroy($this->cache->get_cache_id_from_sql_query($key));
		return true;
	}

	public function deleteItems(array $keys): bool
	{
		foreach ($keys as $key)
		{
			if (!$this->deleteItem($key))
			{
				return false;
			}
		}

		return true;
	}

	public function save(CacheItemInterface $item): bool
	{
		if (!$item instanceof doctrine_cache_item)
		{
			return false;
		}

		return $this->cache->sql_save($item->getKey(), $item->get(), $item->get_ttl());
	}

	public function saveDeferred(CacheItemInterface $item): bool
	{
		if (!$item instanceof doctrine_cache_item)
		{
			return false;
		}

		$this->deferred[$item->getKey()] = $item;
		return true;
	}

	public function commit(): bool
	{
		foreach ($this->deferred as $item)
		{
			if (!$this->save($item))
			{
				return false;
			}
		}

		$this->deferred = [];
		return true;
	}
}
