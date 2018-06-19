<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 25.05.18
 * Time: 20:39
 */

namespace dbv;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Version.php';

/**
 * Class Deploy
 *
 * @package dbv
 */
class Deploy
{
	/**
	 * Constants
	 */
	const MODE_FAST      = 'fast';
	const MODE_INTEGRITY = 'integrity';
	const MODE_VALIDATE  = 'validate';

	/**
	 * @var int
	 */
	protected $currentVersion = 0;

	/**
	 * @var int
	 */
	protected $highestPossibleVersion = 0;

	/**
	 * @var int
	 */
	protected $highestVersion = 0;

	/**
	 * @var null
	 */
	protected $database = null;

	/**
	 * @var string
	 */
	protected $path = '';

	/**
	 * @var string
	 */
	protected $preScript = '';

	/**
	 * @var string
	 */
	protected $postScript = '';

	/**
	 * @var bool
	 */
	protected $compression = false;

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
	public function setPath(string $path)//: void
	{
		$this->path = $path;
	}

	/**
	 * @return string
	 */
	public function getPreScript(): string
	{
		return $this->preScript;
	}

	/**
	 * @param string $preScript
	 */
	public function setPreScript(string $preScript)//: void
	{
		$this->preScript = $preScript;
	}

	/**
	 * @return string
	 */
	public function getPostScript(): string
	{
		return $this->postScript;
	}

	/**
	 * @param string $postScript
	 */
	public function setPostScript(string $postScript)//: void
	{
		$this->postScript = $postScript;
	}

	/**
	 * @return bool
	 */
	public function isCompression(): bool
	{
		return $this->compression;
	}

	/**
	 * @param bool $compression
	 */
	public function setCompression(bool $compression)//: void
	{
		$this->compression = $compression;
	}

	/**
	 * Deploy constructor.
	 *
	 * @param string $config
	 */
	public function __construct(string $configPath)
	{
		if (!file_exists($configPath)) {
			throw new \Exception('Config-File "' . $configPath . '" does not exit.');
		}

		$config = json_decode(
			file_get_contents($configPath),
			true
		);

		if (!$config) {
			throw new \Exception('Invalid JSON in File: "' . $configPath . '".');
		}

		if (!isset($config['database'])
			|| !isset($config['database']['host'])
			|| !isset($config['database']['user'])
			|| !isset($config['database']['password'])
			|| !isset($config['database']['database'])) {
			throw new \Exception('No Database-Credentials specified in the Config-file.' . "\n");
		}
		$database = new Database(
			Database::TYPE_MYSQL,
			$config['database']['host'],
			$config['database']['user'],
			$config['database']['password'],
			$config['database']['database']
		);
		$this->setDatabase($database);

		if (!isset($config['changes']) || !isset($config['changes']['src'])) {
			throw new \Exception('No Source for Database-Changes specified in the Config-file.' . "\n");
		}
		if (!is_dir(__DIR__ . '/' . $config['changes']['src'])) {
			throw new \Exception(
				'Source "' . __DIR__ . '/' . $config['changes']['src'] . '" for Database-Changes is invalid' . "\n"
			);
		}
		foreach (scandir(__DIR__ . '/' . $config['changes']['src']) as $dir) {
			if (!is_dir(__DIR__ . '/' . $config['changes']['src'] . '/' . $dir) || '.' == $dir || '..' == $dir) {
				continue;
			}
			if (!preg_match(
				'/^' . Version::PREFIX . '\d+$/',
				$dir
			)) {
				throw new \Exception(
					'Foldername: "'
					. $dir
					. '" does not start with the prefix: "'
					. Version::PREFIX
					. '" and ends with a number.'
					. "\n"
				);
			}
		}
		$this->setPath(__DIR__ . '/' . $config['changes']['src']);

		if (isset($config['prescript'])) {
			if (!file_exists(__DIR__ . '/' . $config['prescript'])) {
				throw new \Exception('Prescript-File: "' . $config['prescript'] . '" does not exist.' . "\n");
			}
			$this->getDatabase()->validateQuery(file_get_contents(__DIR__ . '/' . $config['prescript']));
			$this->setPreScript(__DIR__ . '/' . $config['prescript']);
		}

		if (isset($config['postscript'])) {
			if (!file_exists(__DIR__ . '/' . $config['postscript'])) {
				throw new \Exception('Postscript-File: "' . $config['postscript'] . '" does not exist.' . "\n");
			}
			$this->getDatabase()->validateQuery(file_get_contents(__DIR__ . '/' . $config['postscript']));
			$this->setPostScript(__DIR__ . '/' . $config['postscript']);
		}

		$this->init();

		echo('Current-Version: ' . $this->getCurrentVersion() . "\n");
		echo('Highest-Version: ' . $this->getHighestVersion() . "\n");
		echo('Highest-PossibleVersion: ' . $this->getHighestPossibleVersion() . "\n");
	}

	/**
	 * @return int
	 */
	protected function selectHighestPossibleVersion(): int
	{
		$highestPossibleVersion = 0;
		$path                   = $this->getPath();
		foreach (scandir($path) as $content) {
			if (!is_dir($path . '/' . $content)) {
				continue;
			}
			if ('.' == $content || '..' == $content) {
				continue;
			}
			$highestPossibleVersion = max(
				$highestPossibleVersion,
				substr(
					$content,
					strlen(Version::PREFIX)
				)
			);
		}

		return $highestPossibleVersion;
	}

	/**
	 *
	 */
	protected function init()
	{
		try {
			$states = $this->getDatabase()->query('SELECT * FROM dbv_state', []);
			if ($states) {
				foreach ($states as $state) {
					switch ($state['name']) {
						case 'current_version':
							$this->setCurrentVersion($state['value']);
							break;
						case 'highest_version':
							$this->setHighestVersion($state['value']);
							break;
					}
				}
			}
			$this->setHighestPossibleVersion($this->selectHighestPossibleVersion());
		} catch (\Exception $exception) {
			/*
			 * it must be the first-time the application is running
			 * make a full backup of the database and add it to the v0-folder
			 * execute the v0-folder queries
			 * recursive-call of init
			 */
			echo('FirstTime initializiation. Ignore Errors and Warnings. :)' . "\n");
			$this->deploy(
				0,
				self::MODE_INTEGRITY
			);
			$this->init();
		}
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
	 * @param string $name
	 * @param string $value
	 */
	public function insertState(
		string $name,
		string $value
	)
	{
		$this->getDatabase()->query(
			'INSERT INTO dbv_state (name, value) VALUES(:name, :value) ON DUPLICATE KEY UPDATE value=VALUES(value)',
			[
				':name'  => $name,
				':value' => $value,
			]
		);
	}

	/**
	 * @param int $deployVersion
	 * @param string $mode
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function deploy(
		int $deployVersion,
		string $mode
	): bool
	{
		if ($deployVersion < 0) {
			throw new \Exception('Version can not be negative, but the value is "' . $deployVersion . '".');
		}

		if ($deployVersion > $this->getHighestPossibleVersion()) {
			throw new \Exception(
				'Version can not be higher than the highest available version, but the value is "'
				. $deployVersion
				. '".'
			);
		}

		if (self::MODE_VALIDATE == $mode) {
			new Version(
				$this->getPath(), $deployVersion, $this->getDatabase()
			);

			return true;
		}

		if ('' != $this->getPreScript()) {
			echo('Executing prescript: "' . str_replace(__DIR__ . '/', '', $this->getPreScript()) . '" ' . "\n");
			$this->getDatabase()->query(
				file_get_contents($this->getPreScript()),
				[]
			);
		}

		echo('Deploying version: "' . $deployVersion . '"' . "\n");
		$deployed = false;
		if ($deployVersion == $this->getCurrentVersion()) {
			$deployed = $this->deploySameVersion($deployVersion);
		} elseif ($deployVersion < $this->getCurrentVersion()) {
			$deployed = $this->deployLowerVersion($deployVersion);
		} elseif ($deployVersion > $this->getCurrentVersion()) {
			$deployed = $this->deployHigherVersion(
				$deployVersion,
				$mode
			);
		}

		if ($deployed) {
			if ('' != $this->getPostScript()) {
				echo('Executing postscript: "' . str_replace(__DIR__ . '/', '', $this->getPostScript()) . '" ' . "\n");
				$this->getDatabase()->query(
					file_get_contents($this->getPostScript()),
					[]
				);
			}
			$this->insertState(
				'current_version',
				$deployVersion
			);
			$this->insertState(
				'highest_version',
				max($deployVersion, $this->getHighestVersion())
			);
		}

		return $deployed;
	}

	/**
	 * @return int
	 */
	public function getHighestVersion(): int
	{
		return $this->highestVersion;
	}

	/**
	 * @param int $highestVersion
	 */
	public function setHighestVersion(int $highestVersion)//: void
	{
		$this->highestVersion = $highestVersion;
	}

	/**
	 * @return int
	 */
	public function getHighestPossibleVersion(): int
	{
		return $this->highestPossibleVersion;
	}

	/**
	 * @param int $highestPossibleVersion
	 */
	public function setHighestPossibleVersion(int $highestPossibleVersion)//: void
	{
		$this->highestPossibleVersion = $highestPossibleVersion;
	}

	/**
	 * @return int
	 */
	public function getCurrentVersion(): int
	{
		return $this->currentVersion;
	}

	/**
	 * @param int $currentVersion
	 */
	public function setCurrentVersion(int $currentVersion)//: void
	{
		$this->currentVersion = $currentVersion;
	}

	/**
	 * check if there are unexecuted changes and execute them
	 *
	 * @param int $deployVersion
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function deploySameVersion(int $deployVersion): bool
	{
		$version = new Version(
			$this->getPath(), $deployVersion, $this->getDatabase()
		);

		return $version->deploy();
	}

	/**
	 * use backup of current-version till deploy-version+1
	 *
	 * @param int $deployVersion
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function deployLowerVersion(int $deployVersion): bool
	{
		for ($executeVersion = $this->getCurrentVersion(); $executeVersion > $deployVersion; $executeVersion--) {
			$version = new Version(
				$this->getPath(), $executeVersion, $this->getDatabase()
			);
			if (!$version->rollback()) {
				return false;
			}
		}

		return true;
	}

	/**
	 * execute change of current-version+1 till deploy-version
	 *
	 * @param int $deployVersion
	 * @param string $mode
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function deployHigherVersion(
		int $deployVersion,
		string $mode
	): bool
	{
		/*if ($mode == self::MODE_FAST) {
			for ($executeVersion = $this->getCurrentVersion() + 2; $executeVersion <= $deployVersion; $executeVersion++)
			{
				$version = new Version(
					$this->getPath(), $executeVersion, $this->getDatabase()
				);
				if(!$version->rollback()) {
					return false;
				}
			}
			$this->deployHigherVersion(
				$deployVersion,
				self::MODE_INTEGRITY
			);

			return true;
		}*/

		for ($executeVersion = $this->getCurrentVersion() + 1; $executeVersion <= $deployVersion; $executeVersion++) {
			$version = new Version(
				$this->getPath(), $executeVersion, $this->getDatabase()
			);
			if (!$version->deploy()) {
				return false;
			}
		}

		return true;
	}
}