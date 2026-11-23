<?php

/**
 * VideoParseControllerTest
 *
 * The video URL parse endpoint (REQ-VID-005): a logged-in user gets the
 * parse as 200 or, for an unusable URL, as 400 with its error.
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
use OCA\LaunchPad\Controller\VideoParseController;
use OCA\LaunchPad\Service\VideoParsingService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \OCA\LaunchPad\Controller\VideoParseController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Controller\UserResponses
 * @uses   \OCA\LaunchPad\Service\VideoParsingService
 */
class VideoParseControllerTest extends TestCase {

	/**
	 * The controller for a user.
	 *
	 * @param string|null $userId The logged-in user.
	 *
	 * @return VideoParseController
	 */
	private function controller(?string $userId): VideoParseController {
		return new VideoParseController(
			request: $this->createMock(originalClassName: IRequest::class),
			parser: new VideoParsingService(),
			context: new UserResponses(userId: $userId, responseHelper: new ResponseHelper(logger: new NullLogger()))
		);
	}//end controller()

	/**
	 * An anonymous caller is unauthorised.
	 *
	 * @return void
	 */
	public function testAnAnonymousCallerIsUnauthorised(): void {
		$this->assertSame(expected: 401, actual: $this->controller(userId: null)->parse(videoUrl: 'https://youtu.be/ABC123')->getStatus());
	}//end testAnAnonymousCallerIsUnauthorised()

	/**
	 * A usable URL answers its canonical embed URL.
	 *
	 * @return void
	 */
	public function testAUsableUrlAnswersItsCanonicalEmbed(): void {
		$response = $this->controller(userId: 'bob')->parse(videoUrl: 'https://www.youtube.com/watch?v=ABC123');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(
			expected: ['isValid' => true, 'sourceType' => 'youtube', 'videoId' => 'ABC123', 'canonicalUrl' => 'https://www.youtube.com/embed/ABC123'],
			actual: $response->getData()
		);
	}//end testAUsableUrlAnswersItsCanonicalEmbed()

	/**
	 * An invalid URL is a 400 with its error.
	 *
	 * @return void
	 */
	public function testAnInvalidUrlIsA400WithItsError(): void {
		$response = $this->controller(userId: 'bob')->parse(videoUrl: 'not a valid URL');

		$this->assertSame(
			expected: [400, ['isValid' => false, 'error' => 'Invalid URL format']],
			actual: [$response->getStatus(), $response->getData()]
		);
	}//end testAnInvalidUrlIsA400WithItsError()
}//end class
