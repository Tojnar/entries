<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Security\Passwords;
use Nextras\Orm\Entity\ToArrayConverter;

class TeamManager implements Nette\Security\IAuthenticator {
	/** @var TeamRepository */
	private $teams;

	private $password;

	public function __construct(TeamRepository $teams, $password) {
		$this->teams = $teams;
		$this->password = $password;
	}

	/**
	 * Performs an authentication.
	 *
	 *
	 * @param array $credentials
	 *
	 * @throws Nette\Security\AuthenticationException
	 *
	 * @return Nette\Security\Identity
	 */
	public function authenticate(array $credentials) {
		list($teamid, $password) = $credentials;

		if ($teamid === 'admin' && $password === $this->password) {
			return new Nette\Security\Identity('admin', 'admin', ['status' => 'admin']);
		}

		$team = $this->teams->getById($teamid);

		if (!$team) {
			throw new Nette\Security\AuthenticationException('The ID of the team is incorrect.', self::IDENTITY_NOT_FOUND);
		} elseif (!Passwords::verify($password, $team->password)) {
			throw new Nette\Security\AuthenticationException('The password is incorrect.', self::INVALID_CREDENTIAL);
		} elseif (Passwords::needsRehash($team->password)) {
			$team->password = Passwords::hash($password);
			$this->teams->persistAndFlush($team);
		}

		$arr = ToArrayConverter::toArray($team);
		unset($arr['persons']);
		unset($arr['password']);
		unset($arr['invoices']);
		unset($arr['lastInvoice']);

		return new Nette\Security\Identity($team->id, 'user', $arr);
	}
}
