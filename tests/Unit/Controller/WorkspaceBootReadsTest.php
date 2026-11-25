<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What the workspace page carries for its view's boot reads.
 *
 * @spec openspec/changes/archive/2026-11-25-workspace-boot-reads-from-initial-state/tasks.md#task-4
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\WorkspaceBootReads;
use OCA\LaunchPad\Db\Tile;
use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\TileService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Tests for {@see WorkspaceBootReads}.
 */
class WorkspaceBootReadsTest extends TestCase {
	/** @var TileService&MockObject */
	private TileService $tiles;

	/** @var AcknowledgementServiceInterface&MockObject */
	private AcknowledgementServiceInterface $acknowledgements;

	/** @var DashboardService&MockObject */
	private DashboardService $dashboards;

	/** @var ActionAuthService&MockObject */
	private ActionAuthService $actionAuth;

	/** @var IUserSession&MockObject */
	private IUserSession $userSession;

	/**
	 * The services the builder reads, as doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->tiles = $this->createMock(originalClassName: TileService::class);
		$this->acknowledgements = $this->createMock(originalClassName: AcknowledgementServiceInterface::class);
		$this->dashboards = $this->createMock(originalClassName: DashboardService::class);
		$this->actionAuth = $this->createMock(originalClassName: ActionAuthService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->userSession->method('getUser')->willReturn($this->createMock(originalClassName: IUser::class));
	}//end setUp()

	/**
	 * The builder under test.
	 *
	 * @param bool $withActor Whether the action check is there.
	 *
	 * @return WorkspaceBootReads The builder.
	 */
	private function reads(bool $withActor=true): WorkspaceBootReads {
		$actor = null;
		if ($withActor === true) {
			$actor = new RequestActor(userSession: $this->userSession, actionAuth: $this->actionAuth);
		}

		return new WorkspaceBootReads(
			tileService: $this->tiles,
			acknowledgements: $this->acknowledgements,
			dashboardService: $this->dashboards,
			logger: new NullLogger(),
			actor: $actor
		);
	}//end reads()

	/**
	 * The three parts are what the three endpoints answer for the caller.
	 *
	 * @return void
	 */
	public function testFirstLoadCarriesTheTilesAcknowledgementsAndDefault(): void {
		$tile = new Tile();
		$tile->setTitle('Mail');
		$this->tiles->method('getUserTiles')->with('pieter')->willReturn([$tile]);
		$pending = ['count' => 1, 'items' => [['announcementKey' => 'a1']]];
		$this->acknowledgements->method('getPending')->with('pieter')->willReturn($pending);
		$this->dashboards->method('getDefaultPreference')->with('pieter')->willReturn('uuid-1');

		$reads = $this->reads()->firstLoad(userId: 'pieter');

		self::assertSame(expected: [$tile->jsonSerialize()], actual: $reads['tiles']);
		self::assertSame(expected: $pending, actual: $reads['pendingAcknowledgements']);
		self::assertSame(expected: ['uuid' => 'uuid-1'], actual: $reads['defaultDashboard']);
	}//end testFirstLoadCarriesTheTilesAcknowledgementsAndDefault()

	/**
	 * A part the action matrix refuses is left out, so its endpoint is asked
	 * and refuses as before; the acknowledgements have no action of their own.
	 *
	 * @return void
	 */
	public function testARefusedPartIsLeftOut(): void {
		$this->actionAuth->method('requireAction')->willThrowException(new OCSForbiddenException());
		$this->tiles->expects(self::never())->method('getUserTiles');
		$this->acknowledgements->method('getPending')->willReturn(['count' => 0, 'items' => []]);

		self::assertSame(
			expected: ['pendingAcknowledgements' => ['count' => 0, 'items' => []]],
			actual: $this->reads()->firstLoad(userId: 'pieter')
		);
	}//end testARefusedPartIsLeftOut()

	/**
	 * Failing acknowledgements are left out, the rest is still carried.
	 *
	 * @return void
	 */
	public function testFailingAcknowledgementsAreLeftOut(): void {
		$this->tiles->method('getUserTiles')->willReturn([]);
		$this->acknowledgements->method('getPending')->willThrowException(new RuntimeException('down'));
		$this->dashboards->method('getDefaultPreference')->willReturn('');

		self::assertSame(
			expected: ['tiles' => [], 'defaultDashboard' => ['uuid' => '']],
			actual: $this->reads()->firstLoad(userId: 'pieter')
		);
	}//end testFailingAcknowledgementsAreLeftOut()

	/**
	 * Without the action check, without a user, or with nothing left, there is no key.
	 *
	 * @return void
	 */
	public function testFirstLoadIsNullWithoutActorUserOrParts(): void {
		self::assertNull(actual: $this->reads(withActor: false)->firstLoad(userId: 'pieter'));
		self::assertNull(actual: $this->reads()->firstLoad(userId: ''));

		$this->actionAuth->method('requireAction')->willThrowException(new OCSForbiddenException());
		$this->acknowledgements->method('getPending')->willThrowException(new RuntimeException('down'));
		self::assertNull(actual: $this->reads()->firstLoad(userId: 'pieter'));
	}//end testFirstLoadIsNullWithoutActorUserOrParts()
}//end class
