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

namespace phpbb\doctrine;

use Doctrine\DBAL\DriverManager;
use phpbb\config_php_file;

/**
 * Doctrine connection factory.
 */
class connection_factory
{
	public static function make_connection(array $params)
	{
		return DriverManager::getConnection($params);
	}

	/**
	 * Constructor.
	 *
	 * This class is uninstantiatable by design.
	 */
	private function __construct()
	{
	}
}
