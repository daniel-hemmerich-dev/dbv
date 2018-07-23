<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 23.07.18
 * Time: 14:48
 */

namespace dbv;

/**
 * Class SSH
 *
 * @package dbv
 */
class SSH
{
	/**
	 * @var null
	 */
	protected $session = null;

	/**
	 * @var null
	 */
	protected $tunnel = null;

	/**
	 * @return null
	 */
	public function getSession()
	{
		return $this->session;
	}

	/**
	 * @param null $session
	 */
	public function setSession($session)//: void
	{
		$this->session = $session;
	}

	/**
	 * @return null
	 */
	public function getTunnel()
	{
		return $this->tunnel;
	}

	/**
	 * @param null $tunnel
	 */
	public function setTunnel($tunnel)//: void
	{
		$this->tunnel = $tunnel;
	}

	/**
	 * SSH constructor.
	 *
	 * @param string $host
	 * @param int $port
	 * @param string $user
	 * @param string $password
	 * @param string $public_key
	 * @param string $private_key
	 * @param string $tunnel_host
	 * @param int $tunnel_port
	 *
	 * @throws \Exception
	 */
	public function __construct(
		string $host,
		int $port,
		string $user,
		string $password,
		string $public_key,
		string $private_key,
		string $tunnel_host,
		int $tunnel_port
	)
	{
		$session = \ssh2_connect(
			$host,
			$port ?? 22,
			[
				'hostkey' => 'ssh-rsa',
			]
		);

		if (!$session) {
			throw new \Exception('SSH Connection failed');
		}

		if (!\ssh2_auth_pubkey_file(
			$session,
			$user,
			$public_key,
			$private_key,
			$password
		)) {
			throw new \Exception('Public Key Authentication Failed');
		}

		$this->setSession($session);

		$tunnel_session = \ssh2_tunnel(
			$this->getSession(),
			$tunnel_host,
			$tunnel_port ?? 22
		);

		if (!$tunnel_session) {
			throw new \Exception('SSH-Tunnel Connection failed');
		}

		$this->setTunnel($tunnel_session);
	}

	/**
	 *
	 */
	public function __destruct()
	{
		\ssh2_disconnect($this->getTunnel());
		\ssh2_disconnect($this->getSession());
	}
}