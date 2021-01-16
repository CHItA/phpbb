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

namespace phpbb\install\module\install_data\task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use phpbb\install\database_task;
use phpbb\install\helper\config;
use phpbb\install\helper\container_factory;
use phpbb\install\helper\database;
use phpbb\install\helper\iohandler\iohandler_interface;
use phpbb\language\language_file_helper;

class add_languages extends database_task
{
	/**
	 * @var Connection
	 */
	protected $db;

	/**
	 * @var iohandler_interface
	 */
	protected $iohandler;

	/**
	 * @var language_file_helper
	 */
	protected $language_helper;

	/**
	 * @var string
	 */
	protected $lang_table;

	/**
	 * @var string
	 */
	protected $profile_fields_table;

	/**
	 * @var string
	 */
	protected $profile_lang_table;

	/**
	 * Constructor
	 *
	 * @param config					$config				Installer config.
	 * @param database					$db_helper			Database helper.
	 * @param iohandler_interface		$iohandler			Installer's input-output handler
	 * @param container_factory			$container			Installer's DI container
	 * @param language_file_helper		$language_helper	Language file helper service
	 */
	public function __construct(config $config,
								database $db_helper,
								iohandler_interface $iohandler,
								container_factory $container,
								language_file_helper $language_helper)
	{
		$this->db				= self::get_doctrine_connection($db_helper, $config);
		$this->iohandler		= $iohandler;
		$this->language_helper	= $language_helper;

		$this->lang_table			= $container->get_parameter('tables.lang');
		$this->profile_fields_table	= $container->get_parameter('tables.profile_fields');
		$this->profile_lang_table	= $container->get_parameter('tables.profile_fields_language');

		parent::__construct(
			$this->db,
			$this->iohandler,
			true
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function run()
	{
		$languages = $this->language_helper->get_available_languages();
		$installed_languages = array();

		$sql = 'INSERT INTO ' . $this->lang_table
			. ' (lang_iso, lang_dir, lang_english_name, lang_local_name, lang_author)'
			. ' VALUES (:lang_iso, :lang_dir, :lang_english_name, :lang_local_name, :lang_author)';
		$lang_stmt = $this->create_prepared_stmt($sql);

		foreach ($languages as $lang_info)
		{
			$this->exec_prepared_stmt($lang_stmt, [
				'lang_iso'			=> $lang_info['iso'],
				'lang_dir'			=> $lang_info['iso'],
				'lang_english_name'	=> htmlspecialchars($lang_info['name']),
				'lang_local_name'	=> htmlspecialchars($lang_info['local_name'], ENT_COMPAT, 'UTF-8'),
				'lang_author'		=> htmlspecialchars($lang_info['author'], ENT_COMPAT, 'UTF-8'),
			]);

			$installed_languages[] = (int) $this->get_last_insert_id();
		}

		try
		{
			$rows = $this->db->fetchAllAssociative('SELECT * FROM ' . $this->profile_fields_table);
		}
		catch (Exception $e)
		{
			$this->iohandler->add_error_message('INST_ERR_DB', $e->getMessage());
			$rows = [];
		}

		$sql = 'INSERT INTO ' . $this->profile_lang_table
			. ' (field_id, lang_id, lang_name, lang_explain, lang_default_value)'
			. " VALUES (:field_id, :lang_id, :lang_name, '', '')";
		$stmt = $this->create_prepared_stmt($sql);
		foreach ($rows as $row)
		{
			foreach ($installed_languages as $lang_id)
			{
				$this->exec_prepared_stmt($stmt, [
					'field_id'				=> $row['field_id'],
					'lang_id'				=> $lang_id,

					// Remove phpbb_ from field name
					'lang_name'				=> strtoupper(substr($row['field_name'], 6)),
				]);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	static public function get_step_count() : int
	{
		return 1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_task_lang_name() : string
	{
		return 'TASK_ADD_LANGUAGES';
	}
}
