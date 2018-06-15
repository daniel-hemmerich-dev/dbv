<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 25.05.18
 * Time: 21:10
 */

namespace dbv;

/**
 * Class Database
 *
 * @package dbv
 */
class Database
{
	const TYPE_MYSQL = 'mysql';
	const DUMP_SPLIT = 1000;

	/**
	 * @var null
	 */
	protected $pdo = null;

	/**
	 * @var string
	 */
	protected $type = self::TYPE_MYSQL;

	/**
	 * @var string
	 */
	protected $host = '';

	/**
	 * @var string
	 */
	protected $user = '';

	/**
	 * @var string
	 */
	protected $password = '';

	/**
	 * @var string
	 */
	protected $name = '';

	/**
	 * Database constructor.
	 *
	 * @param string $type
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 * @param string $name
	 */
	public function __construct(
		string $type,
		string $host,
		string $user,
		string $password,
		string $name
	)
	{
		$pdo = new \PDO(
			$type . ':dbname=' . $name . ';host=' . $host, $user, $password, [
				\PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_CASE             => \PDO::CASE_NATURAL,
				\PDO::ATTR_ORACLE_NULLS     => \PDO::NULL_EMPTY_STRING,
				\PDO::ATTR_EMULATE_PREPARES => true,
			]
		);

		$pdo->query('SET SESSION lock_wait_timeout = 31536000');
		$pdo->query('SET SESSION interactive_timeout = 28800');
		$pdo->query('SET SESSION wait_timeout = 28800');

		$this->setPdo($pdo);
		$this->setType($type);
		$this->setHost($host);
		$this->setUser($user);
		$this->setPassword($password);
		$this->setName($name);
	}

	/**
	 * @return null
	 */
	public function getPdo(): \PDO
	{
		return $this->pdo;
	}

	/**
	 * @param null $pdo
	 */
	public function setPdo(\PDO $pdo): void
	{
		$this->pdo = $pdo;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	public function setType(string $type): void
	{
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getHost(): string
	{
		return $this->host;
	}

	/**
	 * @param string $host
	 */
	public function setHost(string $host): void
	{
		$this->host = $host;
	}

	/**
	 * @return string
	 */
	public function getUser(): string
	{
		return $this->user;
	}

	/**
	 * @param string $user
	 */
	public function setUser(string $user): void
	{
		$this->user = $user;
	}

	/**
	 * @return string
	 */
	public function getPassword(): string
	{
		return $this->password;
	}

	/**
	 * @param string $password
	 */
	public function setPassword(string $password): void
	{
		$this->password = $password;
	}

	/**
	 * @param string $file
	 * @param array $tableWhitelist
	 */
	public function dump(
		string $file,
		array $tableWhitelist
	)
	{
		$tables = $this->query(
			'SELECT table_name FROM information_schema.tables where table_schema=:database',
			['database' => $this->getName()]
		);

		foreach ($tables as $table) {
			if (!in_array(
				$table['table_name'],
				$tableWhitelist
			)) {
				echo('Table: "' . $table['table_name'] . '" skipped during backup, because no changes detected' . "\n");
				continue;
			}

			echo('Backing-up table: ' . $table['table_name'] . "\n");

			$createTable = $this->query(
				'SHOW CREATE TABLE ' . $table['table_name'],
				[]
			);

			$resultTable = $this->query(
				'SELECT * FROM ' . $table['table_name'],
				[]
			);
			file_put_contents(
				$file,
				"-- Dump of Table " . $table['table_name'] . "\nDROP TABLE IF EXISTS `" . $table['table_name'] . "`;\n",
				FILE_APPEND
			);
			file_put_contents(
				$file,
				$createTable[0]['Create Table'] . ";\n\n",
				FILE_APPEND
			);

			if (0 == count($resultTable)) {
				continue;
			}

			file_put_contents(
				$file,
				"LOCK TABLES `" . $table['table_name'] . "` WRITE;\n",
				FILE_APPEND
			);

			$resultTableChunks = array_chunk(
				$resultTable,
				self::DUMP_SPLIT,
				true
			);
			foreach ($resultTableChunks as $tableChunk) {
				$insertTable = "INSERT INTO `"
					. $table['table_name']
					. "` ("
					. implode(', ', array_keys($resultTable[0]))
					. ") \nVALUES";
				foreach ($tableChunk as $row) {
					$insertTable .= "\n(";
					foreach ($row as $value) {
						$insertTable .= "'" . $value . "',";
					}
					$insertTable = substr(
						$insertTable,
						0,
						-1
					);
					$insertTable .= "),";
				}
				$insertTable = substr_replace(
					$insertTable,
					';',
					-1
				);
				file_put_contents(
					$file,
					$insertTable . "\n",
					FILE_APPEND
				);
			}

			file_put_contents(
				$file,
				"UNLOCK TABLES;\n\n\n",
				FILE_APPEND
			);
		}
	}

	/**
	 * @param string $query
	 */
	public function validateQuery(string $query)
	{
		$this->pdo->prepare($query);
	}

	/**
	 * @param string $query
	 * @param array $parameter
	 *
	 * @return null
	 */
	public function query(
		string $query,
		array $parameter
	)
	{
		$statement = $this->pdo->prepare($query);
		$statement->execute($parameter);
		try {
			return $statement->fetchAll(\PDO::FETCH_ASSOC);
		} catch (\Exception $exception) {
			// non fetchable-statements
			return null;
		}
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
	public function setName(string $name): void
	{
		$this->name = $name;
	}
}