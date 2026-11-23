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
				['status' => 403, 'body' => ['error' => 'read_only_token', 'message' => 'This token does not permit write operations. Token scope: mode=read']],
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

	/**
	 * An interactions token keeps only the request filters on its allowed
	 * dimensions; the pinned context always wins; a read token keeps only the
	 * pinned context (task 33, filter scope).
	 *
	 * @return void
	 */
	public function testFilterDimensionsAreRestrictedToAllowedFilters(): void {
		$service  = new ScopeEnforcementService();
		$interact = ['mode' => 'read-with-interactions', 'allowedFilters' => ['status', 'gemeente']];
		$request  = ['status' => 'open', 'periode' => '2026', 'gemeente' => 'Rotterdam'];

		self::assertSame(
			['status' => 'open', 'gemeente' => 'Amsterdam'],
			$service->restrictFilters($request, $interact, ['gemeente' => 'Amsterdam'])
		);
		self::assertSame(['status' => 'open', 'gemeente' => 'Rotterdam'], $service->restrictFilters($request, $interact, []));
		self::assertSame(['gemeente' => 'Amsterdam'], $service->restrictFilters($request, ['mode' => 'read', 'allowedFilters' => ['status']], ['gemeente' => 'Amsterdam']));
		self::assertSame([], $service->restrictFilters($request, ['mode' => 'read-with-interactions'], []));
	}//end testFilterDimensionsAreRestrictedToAllowedFilters()

	/**
	 * The action of a write is read from the last path segment; anything
	 * else names none, so an interactions token refuses it as well.
	 *
	 * @return void
	 */
	public function testTheActionOfAWriteComesFromItsPath(): void {
		$service = new ScopeEnforcementService();

		self::assertSame('export', $service->actionOf('/api/dashboards/D/export'));
		self::assertSame('drillDown', $service->actionOf('/api/dashboards/D/drill-down/'));
		self::assertSame('drillDown', $service->actionOf('/api/dashboards/D/DrillDown'));
		self::assertSame('interact', $service->actionOf('/api/dashboards/D/filters'));
		self::assertSame('interact', $service->actionOf('/api/widgets/W/interactions'));
		self::assertNull($service->actionOf('/api/dashboards/D/widgets/new'));
		self::assertSame(
			'action_not_in_scope',
			$service->refusalFor('POST', $service->actionOf('/api/dashboards/D/widgets/new'), ['mode' => 'read-with-interactions', 'allowedActions' => ['interact']])['body']['error'] ?? null
		);
	}//end testTheActionOfAWriteComesFromItsPath()

}//end class
