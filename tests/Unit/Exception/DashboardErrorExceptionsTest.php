<?php

/**
 * DashboardErrorExceptionsTest
 *
 * Pins the messages and response codes of three exceptions no test built:
 * the children-exist refusal, the initial-state contract violation (also as
 * InitialStateBuilder raises it) and the expired-share answer, which must
 * not reveal that the share existed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Exception;

use OCA\LaunchPad\Exception\DashboardHasChildrenException;
use OCA\LaunchPad\Exception\MissingInitialStateException;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Exception\ShareExpiredException;
use OCA\LaunchPad\Service\InitialState\Page;
use OCA\LaunchPad\Service\InitialStateBuilder;
use OCP\AppFramework\Services\IInitialState;
use PHPUnit\Framework\TestCase;

/**
 * Messages and codes of the dashboard error exceptions.
 *
 * @covers \OCA\LaunchPad\Exception\DashboardHasChildrenException
 * @covers \OCA\LaunchPad\Exception\MissingInitialStateException
 * @covers \OCA\LaunchPad\Exception\ShareExpiredException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 * @uses   \OCA\LaunchPad\Service\InitialStateBuilder
 * @uses   \OCA\LaunchPad\Service\InitialState\Page
 */
class DashboardErrorExceptionsTest extends TestCase {

	/**
	 * The refusal names the child count and how to delete the subtree.
	 *
	 * @return void
	 */
	public function testChildrenRefusalNamesTheCountAndTheCascadeOption(): void {
		$e = new DashboardHasChildrenException(childCount: 3);

		$this->assertSame('Dashboard has 3 children. Use ?cascade=true to delete the subtree.', $e->getMessage());
		$this->assertSame(3, $e->getChildCount());
		$this->assertSame('dashboard_has_children', DashboardHasChildrenException::ERROR_CODE);
	}//end testChildrenRefusalNamesTheCountAndTheCascadeOption()

	/**
	 * The contract violation names page and key, as a 500 with a stable code.
	 *
	 * @return void
	 */
	public function testContractViolationNamesPageAndKey(): void {
		$e = new MissingInitialStateException(page: 'admin', key: 'widgets');

		$this->assertSame(
			'LaunchPad initial-state contract violation: page "admin" requires key "widgets" but it was not set on the builder before apply().',
			$e->getMessage()
		);
		$this->assertSame(['missing_initial_state_key', 500], [$e->getErrorCode(), $e->getHttpStatus()]);
	}//end testContractViolationNamesPageAndKey()

	/**
	 * The builder raises it for the first missing key and provides nothing.
	 *
	 * @return void
	 */
	public function testTheBuilderRaisesItBeforeProvidingAnything(): void {
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->never())->method('provideInitialState');

		$builder = (new InitialStateBuilder(initialState: $initialState, page: Page::ADMIN))
			->setAllGroups([])
			->setConfiguredGroups([]);

		try {
			$builder->apply();
			$this->fail('apply() should refuse an incomplete admin state.');
		} catch (MissingInitialStateException $e) {
			$this->assertStringContainsString('page "admin" requires key "widgets"', $e->getMessage());
		}
	}//end testTheBuilderRaisesItBeforeProvidingAnything()

	/**
	 * An expired share answers like a missing one: 404, "Share not found".
	 *
	 * @return void
	 */
	public function testAnExpiredShareLooksLikeAMissingOne(): void {
		$e = new ShareExpiredException();

		$this->assertInstanceOf(ResourceException::class, $e);
		$this->assertSame(
			['Share not found', 'Share not found', 'share_expired', 404],
			[$e->getMessage(), $e->getDisplayMessage(), $e->getErrorCode(), $e->getHttpStatus()]
		);
		$this->assertSame('Gone', (new ShareExpiredException(message: 'Gone'))->getDisplayMessage());
	}//end testAnExpiredShareLooksLikeAMissingOne()
}//end class
