<?php

/**
 * ScopeEnforcementService unit tests (REQ-EMB-004).
 *
 * @category  Test
 * @package   Unit\Service\Embed
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Service\Embed\ScopeEnforcementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Read-only and allowed-action enforcement.
 */
#[CoversClass(ScopeEnforcementService::class)]
class ScopeEnforcementServiceTest extends TestCase {

	/**
	 * A read token refuses every write and lets GET and HEAD through.
	 *
	 * @return void
	 */
	public function testAReadTokenRefusesEveryWrite(): void {
		$scope = new ScopeEnforcementService();
		$read = ['mode' => 'read', 'allowedActions' => ['interact']];

		foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'delete'] as $method) {
			self::assertSame(
				['status' => 403, 'body' => ['error' => 'scope_violation', 'message' => 'This token does not permit write operations. Token scope: mode=read']],
				$scope->refusalFor($method, 'interact', $read),
				$method
			);
		}

		self::assertNull($scope->refusalFor('GET', null, $read));
		self::assertNull($scope->refusalFor('head', null, $read));
		self::assertSame(403, $scope->refusalFor('POST', 'interact', [])['status'], 'no mode is read');
		self::assertSame(403, $scope->refusalFor('POST', 'interact', ['mode' => 'admin', 'allowedActions' => ['interact']])['status'], 'an unknown mode is read');
	}//end testAReadTokenRefusesEveryWrite()

	/**
	 * An interactions token runs only the actions it names.
	 *
	 * @return void
	 */
	public function testAnInteractionsTokenRunsOnlyItsActions(): void {
		$scope = new ScopeEnforcementService();
		$interact = ['mode' => 'read-with-interactions', 'allowedActions' => ['interact', 'drillDown']];

		self::assertNull($scope->refusalFor('POST', 'drillDown', $interact));
		self::assertSame(
			['status' => 403, 'body' => ['error' => 'action_not_in_scope', 'message' => "The action 'export' is not permitted by this token. Allowed actions: interact, drillDown"]],
			$scope->refusalFor('POST', 'export', $interact)
		);
		self::assertSame(403, $scope->refusalFor('POST', null, $interact)['status'], 'a write that names no action');
		self::assertSame(
			"The action 'interact' is not permitted by this token. Allowed actions: none",
			$scope->enforceActionScope('interact', ['mode' => 'read-with-interactions'])['body']['message']
		);
	}//end testAnInteractionsTokenRunsOnlyItsActions()

	/**
	 * The token's filter context wins over the request's filters.
	 *
	 * @return void
	 */
	public function testTheFilterContextIsPinned(): void {
		self::assertSame(
			['periode' => '2026', 'gemeente' => 'Amsterdam'],
			(new ScopeEnforcementService())->applyFilterContext(['periode' => '2026', 'gemeente' => 'Rotterdam'], ['gemeente' => 'Amsterdam'])
		);
	}//end testTheFilterContextIsPinned()

}//end class
