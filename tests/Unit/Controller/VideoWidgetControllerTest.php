<?php

/**
 * VideoWidgetControllerTest
 *
 * The video file endpoint (REQ-VID-006): it answers only a logged-in viewer
 * who may see the placement, and maps each refusal of the file service to
 * its status without an address.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Controller\UserResponses;
use OCA\LaunchPad\Controller\VideoWidgetController;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\VideoFileService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \OCA\LaunchPad\Controller\VideoWidgetController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Controller\UserResponses
 */
class VideoWidgetControllerTest extends TestCase {

	/**
	 * The controller for a user, a visibility answer and a service result.
	 *
	 * @param string|null $userId   The logged-in user.
	 * @param bool        $mayView  Whether the user may see the placement.
	 * @param array       $resolved What the file service answers.
	 *
	 * @return VideoWidgetController
	 */
	private function controller(?string $userId, bool $mayView, array $resolved = ['status' => 'ok', 'streamingUrl' => '/x']): VideoWidgetController {
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canViewPlacement')->willReturn($mayView);

		$files = $this->createMock(VideoFileService::class);
		$files->method('resolve')->willReturn($resolved);
		if ($mayView === false || $userId === null) {
			$files->expects($this->never())->method('resolve');
		}

		return new VideoWidgetController(
			request: $this->createMock(IRequest::class),
			videoFiles: $files,
			permissions: $permissions,
			context: new UserResponses(userId: $userId, responseHelper: new ResponseHelper(logger: new NullLogger()))
		);
	}//end controller()

	public function testAnAnonymousCallerIsUnauthorised(): void {
		$this->assertSame(401, $this->controller(userId: null, mayView: true)->file(placementId: 9)->getStatus());
	}

	public function testAViewerWhoMayNotSeeThePlacementIsForbiddenBeforeTheFileIsLookedUp(): void {
		$this->assertSame(403, $this->controller(userId: 'bob', mayView: false)->file(placementId: 9)->getStatus());
	}

	public function testAPlayableFileAnswersItsAddress(): void {
		$response = $this->controller(userId: 'bob', mayView: true, resolved: ['status' => 'ok', 'streamingUrl' => '/remote.php/dav/files/bob/a.webm'])->file(placementId: 9);

		$this->assertSame([200, ['streamingUrl' => '/remote.php/dav/files/bob/a.webm']], [$response->getStatus(), $response->getData()]);
	}

	public function testEachRefusalHasItsStatusAndNoAddress(): void {
		foreach (['not_found' => 404, 'not_video' => 422, 'not_accessible' => 403, 'no_file' => 400] as $status => $http) {
			$response = $this->controller(userId: 'bob', mayView: true, resolved: ['status' => $status])->file(placementId: 9);

			$this->assertSame([$http, ['error' => $status]], [$response->getStatus(), $response->getData()], $status);
		}
	}
}//end class
