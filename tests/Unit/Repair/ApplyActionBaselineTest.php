<?php

/**
 * ApplyActionBaselineTest
 *
 * Unit tests for {@see \OCA\LaunchPad\Repair\ApplyActionBaseline} — the
 * upgrade path that brings ALREADY-INSTALLED instances onto the ADR-023
 * non-admin baseline.
 *
 * REGRESSION GUARD: `InitializeActions` only seeds when the matrix is empty,
 * so shipping a broader seed alone fixes nothing on an existing instance —
 * it stays on the all-admin matrix forever. This step closes that gap while
 * preserving deliberate admin customisation, and records a version marker so
 * it cannot re-broaden an action an admin later narrowed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Repair\ApplyActionBaseline;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the baseline-application repair step.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyActionBaselineTest extends TestCase {

	/** @var ActionAuthService&MockObject */
	private $actionAuth;

	/** @var IAppConfig&MockObject */
	private $appConfig;

	/** @var IOutput&MockObject */
	private $output;

	private ApplyActionBaseline $step;

	/**
	 * Set up fresh mocks per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->output = $this->createMock(IOutput::class);

		$this->step = new ApplyActionBaseline(
			actionAuth: $this->actionAuth,
			appConfig: $this->appConfig,
			logger: $this->createMock(LoggerInterface::class),
		);
	}//end setUp()

	/**
	 * The step is named so `occ upgrade` output is legible.
	 *
	 * @return void
	 */
	public function testHasAName(): void {
		$this->assertNotSame('', $this->step->getName());
	}//end testHasAName()

	/**
	 * The core upgrade behaviour: an entry still holding the pristine
	 * `["admin"]` default is broadened to the shipped baseline.
	 *
	 * @return void
	 */
	public function testPristineAdminOnlyEntryIsBroadened(): void {
		$this->appConfig->method('getValueInt')->willReturn(0);
		$this->actionAuth->method('getMatrix')->willReturn(
			[
				'dashboard.list' => ['admin'],
				'analytics.instance-summary' => ['admin'],
			]
		);

		$written = null;
		$this->actionAuth
			->expects($this->once())
			->method('setMatrix')
			->willReturnCallback(
				function (array $matrix) use (&$written) {
					$written = $matrix;
				}
			);

		$this->step->run($this->output);

		$this->assertContains(
			ActionAuthService::GROUP_ALL_USERS,
			$written['dashboard.list'],
			'dashboard.list must be broadened to the baseline.'
		);
		$this->assertSame(
			['admin'],
			$written['analytics.instance-summary'],
			'Administrative actions must stay admin-only.'
		);
	}//end testPristineAdminOnlyEntryIsBroadened()

	/**
	 * An admin-customized entry is NEVER rewritten — a deliberate grant to
	 * a specific group must survive the upgrade untouched.
	 *
	 * @return void
	 */
	public function testAdminCustomizedEntryIsPreserved(): void {
		$this->appConfig->method('getValueInt')->willReturn(0);
		$this->actionAuth->method('getMatrix')->willReturn(
			['dashboard.list' => ['admin', 'editors']]
		);

		$written = null;
		$this->actionAuth
			->method('setMatrix')
			->willReturnCallback(
				function (array $matrix) use (&$written) {
					$written = $matrix;
				}
			);

		$this->step->run($this->output);

		$this->assertSame(
			['admin', 'editors'],
			$written['dashboard.list'],
			'An admin-customized entry must not be rewritten.'
		);
	}//end testAdminCustomizedEntryIsPreserved()

	/**
	 * The version marker is actually WRITTEN. A version gate whose key is
	 * never written is not a gate — it just re-runs on every upgrade and
	 * silently undoes an admin's later narrowing.
	 *
	 * @return void
	 */
	public function testVersionMarkerIsWritten(): void {
		$this->appConfig->method('getValueInt')->willReturn(0);
		$this->actionAuth->method('getMatrix')->willReturn(['dashboard.list' => ['admin']]);

		$this->appConfig
			->expects($this->once())
			->method('setValueInt')
			->with('launchpad', 'actions_baseline_version', $this->greaterThan(0));

		$this->step->run($this->output);
	}//end testVersionMarkerIsWritten()

	/**
	 * A second run is a no-op: the matrix is not rewritten once the marker
	 * records the baseline as applied.
	 *
	 * @return void
	 */
	public function testAlreadyAppliedRunIsANoOp(): void {
		$this->appConfig->method('getValueInt')->willReturn(99);

		$this->actionAuth->expects($this->never())->method('setMatrix');
		$this->appConfig->expects($this->never())->method('setValueInt');

		$this->step->run($this->output);
	}//end testAlreadyAppliedRunIsANoOp()

	/**
	 * An action absent from the stored matrix entirely (installed before
	 * the action existed) is seeded with the baseline value.
	 *
	 * @return void
	 */
	public function testMissingEntryIsSeededWithTheBaseline(): void {
		$this->appConfig->method('getValueInt')->willReturn(0);
		$this->actionAuth->method('getMatrix')->willReturn([]);

		$written = null;
		$this->actionAuth
			->method('setMatrix')
			->willReturnCallback(
				function (array $matrix) use (&$written) {
					$written = $matrix;
				}
			);

		$this->step->run($this->output);

		$this->assertArrayHasKey('dashboard.list', $written);
		$this->assertContains(
			ActionAuthService::GROUP_ALL_USERS,
			$written['dashboard.list']
		);
	}//end testMissingEntryIsSeededWithTheBaseline()
	/**
	 * REQ-ROLE-006: an instance that already applied baseline v1 receives
	 * `admin.get-my-role` from v2, so `GET /api/me/role` stops answering 403
	 * to every non-admin — the only users it exists for.
	 *
	 * @return void
	 */
	public function testAnInstanceAtV1GainsTheOwnRoleLookup(): void {
		$this->appConfig->method('getValueInt')->willReturn(1);
		$this->actionAuth->method('getMatrix')->willReturn([
			'admin.get-my-role' => ['admin'],
		]);

		$written = null;
		$this->actionAuth
			->expects($this->once())
			->method('setMatrix')
			->willReturnCallback(
				function (array $matrix) use (&$written) {
					$written = $matrix;
				}
			);

		$this->step->run($this->output);

		$this->assertIsArray($written, 'v2 must rewrite the matrix for an instance at v1');
		$this->assertArrayHasKey('admin.get-my-role', $written);
		$this->assertContains(
			ActionAuthService::GROUP_ALL_USERS,
			$written['admin.get-my-role'],
			'the own-role lookup must be open to every signed-in user after v2'
		);
	}//end testAnInstanceAtV1GainsTheOwnRoleLookup()

	/**
	 * An instance at v1 is NOT re-broadened wholesale. An action an admin has
	 * since set back to admin-only looks exactly like the pristine default;
	 * re-applying the whole baseline would overrule that decision, so v2
	 * touches only what it added.
	 *
	 * @return void
	 */
	public function testAnInstanceAtV1KeepsAnAdminsLaterRestriction(): void {
		$this->appConfig->method('getValueInt')->willReturn(1);
		$this->actionAuth->method('getMatrix')->willReturn([
			'dashboard.create' => ['admin'],
			'admin.get-my-role' => ['admin'],
		]);

		$written = null;
		$this->actionAuth->method('setMatrix')->willReturnCallback(
			function (array $matrix) use (&$written) {
				$written = $matrix;
			}
		);

		$this->step->run($this->output);

		$this->assertSame(
			['admin'],
			$written['dashboard.create'],
			'an admin-only restriction made after v1 must survive the v2 upgrade'
		);
	}//end testAnInstanceAtV1KeepsAnAdminsLaterRestriction()

	/**
	 * An instance at v2 gains the owner's own versions, metadata writes and
	 * rule reads from v3, and nothing else: an action an admin narrowed is kept.
	 *
	 * @return void
	 */
	public function testAnInstanceAtV2GainsTheOwnerActions(): void {
		$this->appConfig->method('getValueInt')->willReturn(2);
		$this->actionAuth->method('getMatrix')->willReturn([
			'dashboard.create' => ['admin'],
			'dashboard-version.list-versions' => ['admin'],
			'dashboard-metadata.set-metadata' => ['admin'],
			'rule.get-rules' => ['admin', 'editors'],
		]);

		$written = null;
		$this->actionAuth->expects($this->once())->method('setMatrix')->willReturnCallback(
			function (array $matrix) use (&$written) {
				$written = $matrix;
			}
		);

		$this->step->run($this->output);

		$opened = [
			'dashboard-version.list-versions',
			'dashboard-version.fetch-version',
			'dashboard-version.create-version',
			'dashboard-version.restore-version',
			'dashboard-metadata.set-metadata',
		];
		foreach ($opened as $action) {
			$this->assertContains(
				needle: ActionAuthService::GROUP_ALL_USERS,
				haystack: $written[$action],
				message: $action . ' must be open to the owner after v3'
			);
		}

		$this->assertSame(expected: ['admin', 'editors'], actual: $written['rule.get-rules'], message: 'an entry an admin changed is kept');
		$this->assertSame(expected: ['admin'], actual: $written['dashboard.create'], message: 'v3 adds only its own actions');
	}//end testAnInstanceAtV2GainsTheOwnerActions()

	/**
	 * An instance at v3 gains the owner's own translation writes from v4, and
	 * nothing else: an action an admin narrowed is kept.
	 *
	 * @return void
	 */
	public function testAnInstanceAtV3GainsTheOwnersTranslationWrites(): void {
		$this->appConfig->method('getValueInt')->willReturn(3);
		$this->actionAuth->method('getMatrix')->willReturn([
			'dashboard.create' => ['admin'],
			'dashboard-translation.create' => ['admin'],
			'dashboard-translation.set-primary' => ['admin', 'editors'],
		]);

		$written = null;
		$this->actionAuth->expects($this->once())->method('setMatrix')->willReturnCallback(
			function (array $matrix) use (&$written) {
				$written = $matrix;
			}
		);

		$this->step->run($this->output);

		$opened = [
			'dashboard-translation.create',
			'dashboard-translation.update',
			'dashboard-translation.destroy',
		];
		foreach ($opened as $action) {
			$this->assertContains(
				needle: ActionAuthService::GROUP_ALL_USERS,
				haystack: $written[$action],
				message: $action . ' must be open to the owner after v4'
			);
		}

		$this->assertSame(
			expected: ['admin', 'editors'],
			actual: $written['dashboard-translation.set-primary'],
			message: 'an entry an admin changed is kept'
		);
		$this->assertSame(expected: ['admin'], actual: $written['dashboard.create'], message: 'v4 adds only its own actions');
	}//end testAnInstanceAtV3GainsTheOwnersTranslationWrites()

	/**
	 * An instance at v4 lets the owner add visibility rules from v5, and an
	 * entry an admin changed is kept.
	 *
	 * @return void
	 */
	public function testAnInstanceAtV4LetsTheOwnerAddRules(): void {
		$this->appConfig->method('getValueInt')->willReturn(4);
		$this->actionAuth->method('getMatrix')->willReturn([
			'rule.add-rule' => ['admin'],
			'rule.update-rule' => ['admin'],
		]);

		$written = null;
		$this->actionAuth->expects($this->once())->method('setMatrix')->willReturnCallback(
			function (array $matrix) use (&$written) {
				$written = $matrix;
			}
		);

		$this->step->run($this->output);

		$this->assertContains(needle: ActionAuthService::GROUP_ALL_USERS, haystack: $written['rule.add-rule']);
		$this->assertSame(expected: ['admin'], actual: $written['rule.update-rule'], message: 'v5 adds only its own action');
	}//end testAnInstanceAtV4LetsTheOwnerAddRules()

	/**
	 * An instance at v5 lets the owner publish, unpublish and schedule from
	 * v6, and an entry an admin changed is kept.
	 *
	 * @return void
	 */
	public function testAnInstanceAtV5LetsTheOwnerPublish(): void {
		$this->appConfig->method('getValueInt')->willReturn(5);
		$this->actionAuth->method('getMatrix')->willReturn([
			'dashboard.publish' => ['admin'],
			'dashboard.unpublish' => ['admin'],
			'dashboard.schedule' => ['admin', 'redactie'],
			'dashboard-lock.force-release' => ['admin'],
		]);

		$written = null;
		$this->actionAuth->expects($this->once())->method('setMatrix')->willReturnCallback(
			function (array $matrix) use (&$written) {
				$written = $matrix;
			}
		);

		$this->step->run($this->output);

		$this->assertContains(needle: ActionAuthService::GROUP_ALL_USERS, haystack: $written['dashboard.publish']);
		$this->assertContains(needle: ActionAuthService::GROUP_ALL_USERS, haystack: $written['dashboard.unpublish']);
		$this->assertSame(expected: ['admin', 'redactie'], actual: $written['dashboard.schedule'], message: 'an entry an admin changed is kept');
		$this->assertSame(expected: ['admin'], actual: $written['dashboard-lock.force-release'], message: 'v6 adds only its own actions');
	}//end testAnInstanceAtV5LetsTheOwnerPublish()
}//end class
