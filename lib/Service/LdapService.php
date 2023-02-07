<?php
/*
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\UserOIDC\Service;

use OCP\AppFramework\QueryException;
use OCP\ILogger;
use OCP\IUser;

class LdapService {

	/**
	 * @var ILogger
	 */
	private $logger;

	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	/**
	 * @param IUser $user
	 * @return bool
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function isLdapDeletedUser(IUser $user): bool {
		$className = $user->getBackendClassName();
		if ($className !== 'LDAP') {
			return false;
		}

		try {
			/** @var \OCA\User_LDAP\User\DeletedUsersIndex */
			$dui = \OC::$server->get(\OCA\User_LDAP\User\DeletedUsersIndex::class);
		} catch (QueryException $e) {
			$this->logger->debug('\OCA\User_LDAP\User\DeletedUsersIndex class not found');
			return false;
		}

		if (!$dui->hasUsers()) {
			return false;
		}
		$disabledUsers = $dui->getUsers();
		$searchDisabledUser = current(
			array_filter($disabledUsers, function ($disabledUser) use ($user) {
				return $disabledUser->getUID() === $user->getUID();
			})
		);
		// did we find the user in the LDAP deleted user list?
		return $searchDisabledUser !== false;
	}

	/**
	 * This triggers User_LDAP::getLDAPUserByLoginName which does a LDAP query with the login filter
	 * so the user ID we got from the OIDC IdP should work as a login in LDAP (the login filter should use a matching attribute)
	 * @param string $userId
	 * @return void
	 */
	public function syncUser(string $userId): void {
		try {
			/** @var \OCA\User_LDAP\User_Proxy */
			$ldapUserProxy = \OC::$server->get(\OCA\User_LDAP\User_Proxy::class);
			$ldapUserProxy->loginName2UserName($userId);
		} catch (QueryException $e) {
			$this->logger->debug('\OCA\User_LDAP\User_Proxy class not found');
		}
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function mapLoginName2UserName(string $userId): string {
		try {
			$proxy = \OC::$server->get(\OCA\User_LDAP\User_Proxy::class);
			$mappedUserId = $proxy->loginName2UserName($userId);
			if ($mappedUserId !== false && $mappedUserId !== $userId) {
				$userId = $mappedUserId;
			}
		} catch (QueryException $e) {
			$this->logger->debug($e->getMessage());
		} finally {
			return $userId;
		}
	}
}
