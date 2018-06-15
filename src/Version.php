<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 25.05.18
 * Time: 22:09
 */

namespace dbv;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Query.php';
require_once __DIR__ . '/Log.php';

/**
 * Class Version
 *
 * @package dbv
 */
class Version
{
	const PREFIX = 'v';
	const BACKUP = 'backup.sql';

	/**
	 * @var string
	 */
	protected $path = '';

	/**
	 * @var int
	 */
	protected $version = 0;

	/**
	 * @var
	 */
	protected $database;

	/**
	 * @var array
	 */
	protected $queries = [];

	/**
	 * Version constructor.
	 *
	 * @param int $version
	 */
	public function __construct(
		string $path,
		int $version,
		Database $database
	)
	{
		$this->setPath($path);
		$this->setVersion($version);
		$this->setDatabase($database);

		$matches = [];
		foreach (scandir($this->getFullpath()) as $queryFile) {
			if ('.' == substr($queryFile, 0, 1) || is_dir($queryFile) || SELF::BACKUP == $queryFile) {
				continue;
			}
			if (!preg_match(
				'/[' . Query::PREFIX . '](\d+)_(.+)\.sql/i',
				$queryFile,
				$matches
			)) {
				throw new \Exception(
					'File: "'
					. $queryFile
					. '" does not match the requirements. Prefix must be: "'
					. Query::PREFIX
					. '" followed by a number and a "_".'
					. "\n"
				);
			}
			if (isset($this->queries[$matches[1]])) {
				throw new \Exception(
					'A query with the same id "' . $matches[1] . '"from file "' . $queryFile . '" already exist.'
				);
			}
			$this->queries[$matches[1]] = new Query(
				$database, $version, $matches[2], file_get_contents($this->getFullpath() . $queryFile)
			);
		}
	}

	/**
	 * @return string
	 */
	protected function getFullpath()
	{
		return $this->getPath() . self::PREFIX . $this->getVersion() . '/';
	}

	/**
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * @param string $path
	 */
	public function setPath(string $path): void
	{
		$this->path = $path;
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
	public function setVersion(int $version): void
	{
		$this->version = $version;
	}

	/**
	 * @return bool
	 */
	public function deploy(): bool
	{
		try {
			$this->backup();
		} catch (\Exception $exception) {
			echo($exception);

			return false;
		}

		try {
			foreach ($this->getQueries() as $query) {
				$query->execute();
			}

			return true;
		} catch (\Exception $exception) {
			echo $exception;
			$this->rollback();

			return false;
		}
	}

	/**
	 * check which changes needs to be executed if it is the current-version, else execute all queries
	 * backup them, add them to the backup-file-name, so it is clear which queries the backup contains
	 */
	protected function backup()
	{
		// add the tables of unexecuted scripts to the backup-table
		$backuplist = [];
		foreach ($this->getQueries() as $query) {
			if (!$query->alreadyExecuted()) {
				$backuplist = array_merge(
					$backuplist,
					$query->getTables()
				);
			}
		}

		echo("Creating Backup\n");
		$backupFile = $this->getFullpath() . SELF::BACKUP;
		$this->getDatabase()->dump(
			$backupFile,
			$backuplist
		);

		if (file_exists($backupFile)) {
			$query = new Query(
				$this->getDatabase(),
				$this->getVersion(),
				'backup_' . md5(implode('', $backuplist)),
				file_get_contents($backupFile)
			);
			$query->insert();
			unlink($backupFile);
		}
	}

	/**
	 * @return mixed
	 */
	public function getDatabase(): Database
	{
		return $this->database;
	}

	/**
	 * @param mixed $database
	 */
	public function setDatabase(Database $database): void
	{
		$this->database = $database;
	}

	/**
	 * @return array
	 */
	public function getQueries(): array
	{
		return $this->queries;
	}

	/**
	 * @param array $queries
	 */
	public function setQueries(array $queries): void
	{
		$this->queries = $queries;
	}

	/**
	 * @return bool
	 */
	public function rollback(): bool
	{
		try {
			echo("Performing a Rollback\n");
			$backups = $this->getDatabase()->query(
				'SELECT name, query, datetime FROM dbv_queries 
						WHERE version=:version AND name LIKE :name 
						ORDER BY datetime DESC',
				[
					':version' => $this->getVersion(),
					':name'    => 'backup_%',
				]
			);
			foreach ($backups as $backup) {
				$startTime = microtime(true);
				$status    = Query::STATUS_OK;
				$message   = '';
				try {
					$this->getDatabase()->query(
						gzuncompress($backup['query']),
						[]
					);
				} catch (\Exception $exception) {
					echo($exception);
					$status  = Query::STATUS_FAILED;
					$message = (string)$exception;
				}
				Log::instance($this->getDatabase())->insert(
					$this->getVersion(),
					$backup['name'],
					$status,
					$message,
					microtime(true) - $startTime
				);
			}

			return true;
		} catch (\Exception $exception) {
			echo($exception);

			return false;
		}
	}
}