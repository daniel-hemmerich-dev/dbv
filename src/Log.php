<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 25.05.18
 * Time: 21:49
 */

namespace dbv;

/**
 * Class Log
 *
 * @package dbv
 */
class Log
{
	const DECIMALS = 3;

	/**
	 * @var null
	 */
	protected $database = null;

	/**
	 * Log constructor.
	 *
	 * @param Database $database
	 */
	protected function __construct(Database $database)
	{
		$this->setDatabase($database);
	}

	/**
	 * @param Database $database
	 *
	 * @return Log|null
	 */
	public static function instance(Database $database)
	{
		static $instance = null;

		if (null === $instance) {
			$instance = new Log($database);
		}

		return $instance;
	}

	/**
	 * @return null
	 */
	public function getDatabase(): Database
	{
		return $this->database;
	}

	/**
	 * @param null $database
	 */
	public function setDatabase(Database $database)//: void
	{
		$this->database = $database;
	}

	/**
	 * @param int $version
	 * @param string $name
	 * @param string $status
	 * @param string $message
	 * @param int $executionTime
	 */
	public function insert(
		int $version,
		string $name,
		string $status,
		string $message,
		float $executionTime
	)
	{
		try {
			$this->getDatabase()->query(
				'INSERT INTO dbv_log (datetime, version, name, status, message, execution_time) 
					VALUES (NOW(), ?, ?, ?, ?, ?)',
				[
					$version,
					$name,
					$status,
					$message,
					number_format(
						$executionTime,
						self::DECIMALS,
						'.',
						''
					),
				]
			);
		} catch (\Exception $exception) {
			if (false === strpos($exception, "dbv_log' doesn't exist")) {
				echo($exception);
			}
		}
	}
}