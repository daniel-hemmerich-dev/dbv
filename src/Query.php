<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 25.05.18
 * Time: 20:40
 */

namespace dbv;

require_once __DIR__ . '/Database.php';

/**
 * Class Query
 *
 * @package dbv
 */
class Query
{
	const PREFIX         = 'dbc';
	const STATUS_OK      = 'OK';
	const STATUS_SKIPPED = 'SKIPPED';
	const STATUS_FAILED  = 'FAILED';

	/**
	 * @var null
	 */
	protected $database = null;

	/**
	 * @var int
	 */
	protected $version = 0;

	/**
	 * @var string
	 */
	protected $name = '';

	/**
	 * @var string
	 */
	protected $content = '';

	/**
	 * @var array
	 */
	protected $tables = [];

	/**
	 * Query constructor.
	 *
	 * @param Database $database
	 * @param string $content
	 */
	public function __construct(
		Database $database,
		int $version,
		string $name,
		string $content
	)
	{
		$database->validateQuery($content);

		$this->setDatabase($database);
		$this->setVersion($version);
		$this->setName($name);
		$this->setContent($content);

		$matches = [];
		preg_match_all(
			'/\b(TRUNCATE|TRUNCATE TABLE|CREATE TABLE IF NOT EXISTS|CREATE TABLE|DROP TABLE IF EXISTS|DROP TABLE|ALTER TABLE|INSERT IGNORE INTO|INSERT INTO|INSERT|UPDATE|SELECT .* FROM|JOIN|DELETE FROM|CREATE INDEX .+ ON)\s+([`]*[a-zA-Z0-9_.$]+[`]*)/im',
			$this->getContent(),
			$matches
		);
		if (!isset($matches[2]) || 0 == count($matches[2])) {
			throw new \Exception('No Tables matched on "' . $this->getName() . '"');
		}
		$this->setTables($matches[2]);
	}

	/**
	 * @return string
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * @param string $content
	 */
	public function setContent(string $content)//: void
	{
		$this->content = $content;
	}

	/**
	 * @return array
	 */
	public function getTables(): array
	{
		return $this->tables;
	}

	/**
	 * @param array $tables
	 */
	public function setTables(array $tables)//: void
	{
		foreach ($tables as $table) {
			$tmp_table = str_replace(
				'`',
				'',
				$table
			);
			$matches   = explode('.', $tmp_table);

			if (2 == count($matches)) {
				if ($matches[0] == $this->getDatabase()->getName()) {
					$this->tables[] = $matches[1];
				}
			} else {
				$this->tables[] = $matches[0];
			}
		}
	}

	/**
	 * @return bool
	 */
	public function alreadyExecuted()
	{
		try {
			$result = $this->getDatabase()->query(
				'SELECT COUNT(*) AS already_exist FROM dbv_queries 
				WHERE version=(SELECT value FROM dbv_state WHERE name=:state_name LIMIT 1) AND name=:name AND hash=:hash LIMIT 1',
				[
					':state_name' => 'current_version',
					':name'       => $this->getName(),
					':hash'       => md5($this->getContent()),
				]
			);

			return 1 == $result[0]['already_exist'];
		} catch (\Exception $exception) {
			if (false === strpos($exception, "dbv_queries' doesn't exist")
				&& false === strpos($exception, "dbv_state' doesn't exist")) {
				echo($exception);
			}

			return false;
		}
	}

	/**
	 *
	 */
	public function insert()
	{
		try {
			$this->getDatabase()->query(
				'INSERT IGNORE INTO dbv_queries (version, name, datetime, hash, query) 
					VALUES(:version, :name, NOW(), :hash, :query)',
				[
					':version' => $this->getVersion(),
					':name'    => $this->getName(),
					':hash'    => md5($this->getContent()),
					':query'   => $this->getContent()
				]
			);
		} catch (\Exception $exception) {
			if (false === strpos($exception, "dbv_queries' doesn't exist")) {
				echo $exception;
			}
		}
	}

	/**
	 * @return bool
	 */
	public function execute(): bool
	{
		$startTime = microtime(true);
		$status    = self::STATUS_OK;
		$message   = '';
		try {
			if ($this->alreadyExecuted() || (0 == count($this->getTables()))) {
				//echo(' -> ' . self::STATUS_SKIPPED . "\n");
				$status = self::STATUS_SKIPPED;
				echo('Skipped "' . $this->getName() . '"' . "\n");
			} else {
				echo('Executing "' . $this->getName() . '"' . "\n");
				$this->getDatabase()->query(
					$this->getContent(),
					[]
				);
				//echo(' -> ' . self::STATUS_OK . "\n");
				$this->insert();
			}
		} catch (\Exception $exception) {
			//echo(' -> ' . self::STATUS_FAILED . "\n");
			echo($exception);
			$status  = self::STATUS_FAILED;
			$message = (string)$exception;
		}
		Log::instance($this->getDatabase())->insert(
			$this->getVersion(),
			$this->getName(),
			$status,
			$message,
			microtime(true) - $startTime
		);

		return $status != self::STATUS_FAILED;
	}

	/**
	 * @return null
	 */
	public function getDatabase()
	{
		return $this->database;
	}

	/**
	 * @param null $database
	 */
	public function setDatabase($database)//: void
	{
		$this->database = $database;
	}

	/**
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * @param int $version
	 */
	public function setVersion(int $version)//: void
	{
		$this->version = $version;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name)//: void
	{
		$this->name = $name;
	}
}