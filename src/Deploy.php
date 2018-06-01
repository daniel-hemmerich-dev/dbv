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
	const MODE_FAST      = 'fast';
	const MODE_INTEGRITY = 'integrity';

	/**
	 * @var array
	 */
	protected $config = [];

	/**
	 * @var int
	 */
	protected $currentVersion = 0;

	/**
	 * @var int
	 */
	protected $highestPossibleVersion = 1;

	/**
	 * @var int
	 */
	protected $highestVersion = 1;

	/**
	 * @var null
	 */
	protected $database = null;

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

		$this->setConfig(
			$config
		);

		$database = new Database(
			Database::TYPE_MYSQL,
			$config['database']['host'],
			$config['database']['user'],
			$config['database']['password'],
			$config['database']['database']
		);
		$this->setDatabase($database);

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
		$path                   = __DIR__ . '/' . $this->config['changes']['src'];
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
	public function setDatabase(Database $database): void
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

		echo('Deploying version: ' . $deployVersion . "\n");
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
	public function setHighestVersion(int $highestVersion): void
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
	public function setHighestPossibleVersion(int $highestPossibleVersion): void
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
	public function setCurrentVersion(int $currentVersion): void
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
			$this->config['changes']['src'], $deployVersion, $this->getDatabase()
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
				$this->config['changes']['src'], $executeVersion, $this->getDatabase()
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
					$this->config['changes']['src'], $executeVersion, $this->getDatabase()
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
				$this->config['changes']['src'], $executeVersion, $this->getDatabase()
			);
			if (!$version->deploy()) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * @param array $config
	 */
	public function setConfig(array $config): void
	{
		$this->config = $config;
	}
}