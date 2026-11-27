<?php

/**
 * AcknowledgedWidgetsControllerTest
 *
 * The list of widgets that ask for confirmation (REQ-ACK-008).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AcknowledgedWidgetsController;
use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AcknowledgedWidgetsController.
 *
 * @covers \OCA\LaunchPad\Controller\AcknowledgedWidgetsController
 */
class AcknowledgedWidgetsControllerTest extends TestCase {

	/**
	 * The list of the service, as `{widgets}`; Nextcloud's attribute check guards it.
	 *
	 * @return void
	 */
	public function testIndex(): void {
		$service = $this->createMock(originalClassName: AcknowledgementServiceInterface::class);
		$service->method('listAcknowledged')->willReturn([['announcementKey' => 'ak-1']]);
		$controller = new AcknowledgedWidgetsController(
			request: $this->createMock(originalClassName: IRequest::class),
			acknowledgements: $service
		);

		$this->assertSame(expected: ['widgets' => [['announcementKey' => 'ak-1']]], actual: $controller->index()->getData());
	}//end testIndex()
}//end class
