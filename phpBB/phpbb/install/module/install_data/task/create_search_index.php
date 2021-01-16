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

use Doctrine\DBAL\Exception;
use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use phpbb\event\dispatcher;
use phpbb\install\database_task;
use phpbb\install\helper\config;
use phpbb\install\helper\container_factory;
use phpbb\install\helper\database;
use phpbb\install\helper\iohandler\iohandler_interface;
use phpbb\search\fulltext_native;
use phpbb\user;

class create_search_index extends database_task
{
	/**
	 * @var auth
	 */
	protected $auth;

	/**
	 * @var \phpbb\config\config
	 */
	protected $config;

	/**
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $conn;

	/**
	 * @var driver_interface
	 */
	protected $db;

	/**
	 * @var iohandler_interface
	 */
	protected $iohandler;

	/**
	 * @var dispatcher
	 */
	protected $phpbb_dispatcher;

	/**
	 * @var user
	 */
	protected $user;

	/**
	 * @var string phpBB root path
	 */
	protected $phpbb_root_path;

	/**
	 * @var string PHP file extension
	 */
	protected $php_ext;

	/**
	 * @var string
	 */
	protected $posts_table;

	/**
	 * Constructor
	 *
	 * @param config				$config				Installer config.
	 * @param database				$db_helper			Database helper.
	 * @param container_factory		$container			Installer's DI container
	 * @param iohandler_interface	$iohandler			IO manager.
	 * @param string				$phpbb_root_path	phpBB root path
	 * @param string				$php_ext			PHP file extension
	 */
	public function __construct(
		config $config,
		database $db_helper,
		container_factory $container,
		iohandler_interface $iohandler,
		string $phpbb_root_path,
		string $php_ext)
	{
		$this->conn = self::get_doctrine_connection($db_helper, $config);

		$this->auth				= $container->get('auth');
		$this->config			= $container->get('config');
		$this->db				= $container->get('dbal.conn');
		$this->iohandler		= $iohandler;
		$this->phpbb_dispatcher = $container->get('dispatcher');
		$this->user 			= $container->get('user');
		$this->phpbb_root_path	= $phpbb_root_path;
		$this->php_ext			= $php_ext;

		parent::__construct($this->conn, $iohandler, true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function run()
	{
		// Make sure fulltext native load update is set
		$this->config->set('fulltext_native_load_upd', 1);

		$error = false;
		$search = new fulltext_native(
			$error,
			$this->phpbb_root_path,
			$this->php_ext,
			$this->auth,
			$this->config,
			$this->db,
			$this->user,
			$this->phpbb_dispatcher
		);

		try
		{
			$sql = 'SELECT post_id, post_subject, post_text, poster_id, forum_id FROM ' . $this->posts_table;
			$rows = $this->conn->fetchAllAssociative($sql);
		}
		catch (Exception $e)
		{
			$this->iohandler->add_error_message('INST_ERR_DB', $e->getMessage());
			$rows = [];
		}

		foreach ($rows as $row)
		{
			$search->index('post', $row['post_id'], $row['post_text'], $row['post_subject'], $row['poster_id'], $row['forum_id']);
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
		return 'TASK_CREATE_SEARCH_INDEX';
	}
}
