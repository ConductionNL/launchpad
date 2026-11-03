<?php

/**
 * ProfileFieldValues
 *
 * The new values of one profile field of one person, with where they came
 * from (REQ-PEX-001).
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

/**
 * Field key, cleaned values, source (self, ldap or nextcloud) and, for a
 * mirrored standard field, its visibility scope.
 */
final class ProfileFieldValues {
	/**
	 * Constructor
	 *
	 * @param string      $fieldKey Field key.
	 * @param string[]    $values   New values (already cleaned).
	 * @param string      $source   self, ldap or nextcloud.
	 * @param string|null $scope    Visibility scope of a mirrored standard field.
	 */
	public function __construct(
		public readonly string $fieldKey,
		public readonly array $values,
		public readonly string $source,
		public readonly ?string $scope = null,
	) {
	}//end __construct()
}//end class
