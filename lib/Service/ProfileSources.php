<?php

/**
 * ProfileSources
 *
 * The account manager and the LDAP provider factory profile values are read from.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Accounts\IAccountManager;
use OCP\LDAP\ILDAPProviderFactory;

/**
 * The account manager and the LDAP provider factory profile values are read from.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileSources {
	/**
	 * Constructor.
	 *
	 * @param IAccountManager      $accountManager Standard profile fields.
	 * @param ILDAPProviderFactory $ldapProviderFactory LDAP attribute access.
	 */
	public function __construct(
		public readonly IAccountManager $accountManager,
		public readonly ILDAPProviderFactory $ldapProviderFactory,
	) {
	}//end __construct()
}//end class
