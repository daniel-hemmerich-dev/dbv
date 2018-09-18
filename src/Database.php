<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 25.05.18
 * Time: 21:10
 */

namespace dbv;

require_once __DIR__ . '/SSH.php';

/**
 * Class Database
 *
 * @package dbv
 */
class Database
{
	const TYPE_MYSQL = 'mysql';

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
	 * @var string
	 */
	protected $charset = '';

	/**
	 * @var int
	 */
	protected $maxAllowedPacked = 0;

	/**
	 * Database constructor.
	 *
	 * @param string $type
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 * @param string $name
	 * @param string $charset
	 */
	public function __construct(
		string $type,
		string $host,
		string $user,
		string $password,
		string $name,
		string $charset
	)
	{
		$this->setType($type);
		$this->setHost($host);
		$this->setUser($user);
		$this->setPassword($password);
		$this->setName($name);
		$this->setCharset($charset);

		$connection = $this->getType() . ':';
		$connection .= 'host=' . $this->getHost() . ';';
		$connection .= 'charset=' . $this->getCharset() . ';';
		if ('' != $this->getName()) {
			$connection .= 'dbname=' . $this->getName() . ';';
		}

		$pdo = new \PDO(
			$connection, $this->getUser(), $this->getPassword(), [
				\PDO::ATTR_ERRMODE          		=> \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_CASE             		=> \PDO::CASE_NATURAL,
				\PDO::ATTR_ORACLE_NULLS     		=> \PDO::NULL_EMPTY_STRING,
				\PDO::ATTR_EMULATE_PREPARES 		=> true,
				\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY	=> true
			]
		);
		$this->setPdo($pdo);

		$this->query('SET SESSION lock_wait_timeout = 31536000', []);
		$this->query('SET SESSION interactive_timeout = 28800', []);
		$this->query('SET SESSION wait_timeout = 28800', []);

		$result = $this->query('SHOW VARIABLES LIKE "max_allowed_packet"', []);
		$this->setMaxAllowedPacked($result[0]['Value']);
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
	public function setPdo(\PDO $pdo)//: void
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
	public function setType(string $type)//: void
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
	public function setHost(string $host)//: void
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
	public function setUser(string $user)//: void
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
	public function setPassword(string $password)//: void
	{
		$this->password = $password;
	}

	/**
	 * @param Database $syncFrom
	 * @param array $tableWhitelist
	 */
	public function synchronize(
		Database $syncFrom,
		array $tableWhitelist
	)
	{
		echo("Synchronization\n");
		$tables = $this->query(
			'SELECT table_name FROM information_schema.tables where table_schema=:database',
			['database' => $this->getName()]
		);

		foreach ($tables as $table) {
			if (!in_array(
				$table['table_name'],
				$tableWhitelist
			)) {
				//echo('Table: "' . $table['table_name'] . '" skipped during synchronization' . "\n");
				continue;
			}

			echo('Synchronizing table: ' . $table['table_name'] . "\n");

			$columnsTo = $this->query(
				'SELECT column_name FROM information_schema.columns WHERE table_schema=:database AND table_name=:table',
				[
					'database' => $this->getName(),
					'table'    => $table['table_name'],
				]
			);

			try {
				$columnsFrom = $syncFrom->query(
					'SELECT column_name FROM information_schema.columns WHERE table_schema=:database AND table_name=:table',
					[
						'database' => $syncFrom->getName(),
						'table'    => $table['table_name'],
					]
				);
			} catch (\Exception $exception) {
				echo $exception;
				continue;
			}

			// diff = to > from
			$intersect       = array_intersect(
				array_column($columnsTo, 'column_name'),
				array_column($columnsFrom, 'column_name')
			);
			$intersectString = implode(
				',',
				$intersect
			);

			// truncate table
			$this->query(
				'TRUNCATE ' . $table['table_name'],
				[]
			);

			// get results to sync from
			$results      = $syncFrom->query(
				'SELECT ' . $intersectString . ' FROM ' . $table['table_name'],
				[]
			);
			$resultChunks = array_chunk(
				$results,
				self::DUMP_SPLIT,
				true
			);

			// insert the intersect between the from and to tables
			foreach ($resultChunks as $chunk) {
				$insertTable = "INSERT IGNORE INTO `"
					. $table['table_name']
					. "` ("
					. implode(', ', array_keys($results[0]))
					. ") \nVALUES";
				foreach ($chunk as $row) {
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
				$this->query(
					$insertTable,
					[]
				);
			}
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
	 * @param string $value
	 *
	 * @return mixed
	 */
	public function quote(string $value)
	{
		return $this->pdo->quote($value);
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

	/**
	 * @return int
	 */
	public function getMaxAllowedPacked(): int
	{
		return $this->maxAllowedPacked;
	}

	/**
	 * @param int $maxAllowedPacked
	 */
	public function setMaxAllowedPacked(int $maxAllowedPacked)//: void
	{
		$this->maxAllowedPacked = $maxAllowedPacked;
	}

	/**
	 * @return string
	 */
	public function getCharset(): string
	{
		return $this->charset;
	}

	/**
	 * @param string $charset
	 */
	public function setCharset(string $charset)//: void
	{
		$this->charset = $charset;
	}
}