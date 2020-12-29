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
use InvalidArgumentException;
use phpbb\config_php_file;
use RuntimeException;

/**
 * Doctrine connection factory.
 */
class connection_configurator
{
	/**
	 * Returns the Doctrine Connection configuration parameters.
	 *
	 * @param config_php_file|array $config The database configuration.
	 *
	 * @return array The Doctrine Connection configuration parameters.
	 */
	public static function get_params($config)
	{
		$params = [];
		if ($config instanceof config_php_file)
		{
			$params = [
				'driver'	=> $config->convert_30_dbms_to_31($config->get('dbms')),
				'dbhost'	=> $config->get('dbhost'),
				'dbuser'	=> $config->get('dbuser'),
				'dbpasswd'	=> $config->get('dbpasswd'),
				'dbname'	=> $config->get('dbname'),
				'dbport'	=> $config->get('dbport'),
			];
		}
		elseif (is_array($config))
		{
			$params = $config;
		}
		else
		{
			throw new InvalidArgumentException('connection_factory::get_params() only takes a config object or an array.');
		}

		return self::convert_parameters_to_doctrine_format($params);
	}

	/**
	 * Maps phpBB's database parameters to Doctrine's format.
	 *
	 * @param array $params phpBB's database config parameters.
	 *
	 * @return array Doctrine DBAL's connection configuration options.
	 */
	private static function convert_parameters_to_doctrine_format(array $params)
	{
		if (!self::is_doctrine_driver($params['driver']))
		{
			$params['driver'] = self::convert_to_doctrine_driver($params['driver']);
		}

		switch ($params['driver'])
		{
			case 'pdo_sqlite':
				$params = self::convert_sqlite_parameters($params);
			break;
			default:
				$p = [
					'driver'	=> $params['driver'],
					'dbname'	=> $params['dbname'],
					'user'		=> $params['dbuser'],
					'password'	=> $params['dbpasswd'],
					'host'		=> $params['dbhost'],
				];

				if (array_key_exists('dbport', $params) && !empty($params['dbport']))
				{
					$p['port'] = (int) $params['dbport'];
				}

				$params = $p;
		}

		return $params;
	}

	/**
	 * Map phpBB's sqlite configuration to Doctrine's configuration options.
	 *
	 * @param $params phpBB's sqlite configuration parameters.
	 *
	 * @return array Doctrine's sqlite configuration options.
	 */
	private static function convert_sqlite_parameters($params)
	{
		$p = [
			'driver'	=> $params['driver'],
			'path'		=> $params['dbhost']
		];

		if (array_key_exists('dbuser', $params) && !empty($params['dbuser']))
		{
			$p['user'] = $params['dbuser'];
		}

		if (array_key_exists('dbpasswd', $params) && !empty($params['dbpasswd']))
		{
			$p['password'] = $params['dbpasswd'];
		}

		return $p;
	}

	/**
	 * Converts phpBB driver names to Doctrine DBAL driver names.
	 *
	 * @param string $driver_name The phpBB driver name.
	 *
	 * @return string The corresponding Doctrine DBAL driver name.
	 *
	 * @throws InvalidArgumentException	When `$driver_name` is an invalid phpBB database driver name.
	 * @throws RuntimeException			When PHP extension for the requested database is missing.
	 */
	private static function convert_to_doctrine_driver(string $driver_name)
	{
		$driver_name = str_replace('phpbb\db\driver', '', $driver_name);
		$driver_name = preg_replace('/mysql$/i', 'mysqli', $driver_name);
		$driver_name = trim($driver_name, '\\');

		switch ($driver_name)
		{
			case 'mssql_odbc':
			case 'mssqlnative':
				$driver_name = (extension_loaded('sqlsrv')) ? 'sqlsrv' : 'pdo_sqlsrv';
				break;
			case 'mysqli':
				$driver_name = (extension_loaded('pdo_mysql')) ? 'pdo_mysql' : 'mysqli';
				break;
			case 'oracle':
				$driver_name = (extension_loaded('oci8')) ? 'oci8' : 'pdo_oci';
				break;
			case 'postgres':
				$driver_name = 'pdo_pgsql';
				break;
			case 'sqlite3':
				$driver_name = 'pdo_sqlite';
				break;
			default:
				throw new InvalidArgumentException('connection_factory::convert_to_doctrine_driver() was supplied an invalid database driver name.');
		}

		if (extension_loaded($driver_name))
		{
			throw new RuntimeException('connection_factory::convert_to_doctrine_driver() PHP extension is missing for the database driver.');
		}

		return $driver_name;
	}

	/**
	 * Check if the driver name is a Doctrine DBAL driver.
	 *
	 * @param string $driver_name The name of the driver.
	 *
	 * @return bool True if the driver is a Doctrine driver, false otherwise.
	 */
	private static function is_doctrine_driver(string $driver_name)
	{
		$drivers = DriverManager::getAvailableDrivers();
		return in_array($driver_name, $drivers);
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
