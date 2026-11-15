<?php

/**
 * Unit tests for VisibilityPreviewController's context validation and failure handling.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use DateTime;
use Exception;
use OCA\LaunchPad\Controller\VisibilityPreviewController;
use OCA\LaunchPad\Service\ConditionalService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * What a visibility preview accepts as context, and how a failing preview answers.
 *
 * @covers \OCA\LaunchPad\Controller\VisibilityPreviewController
 */
class VisibilityPreviewControllerContextTest extends TestCase {

	/**
	 * The conditional service double.
	 *
	 * @var ConditionalService&MockObject
	 */
	private ConditionalService $service;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->service = $this->createMock(ConditionalService::class);
	}//end setUp()

	/**
	 * The controller under test, for the given session user.
	 *
	 * @param string|null $userId The session user.
	 *
	 * @return VisibilityPreviewController
	 */
	private function controller(?string $userId = 'alice'): VisibilityPreviewController {
		return new VisibilityPreviewController($this->createMock(IRequest::class), $this->service, $userId);
	}//end controller()

	/**
	 * Without a session user the preview is unauthorised.
	 *
	 * @return void
	 */
	public function testAnAnonymousPreviewIsUnauthorised(): void {
		$this->service->expects(self::never())->method('previewRules');

		self::assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(null)->preview([], [])->getStatus());
	}//end testAnAnonymousPreviewIsUnauthorised()

	/**
	 * Groups that are not a list of strings, or a date that is not a parseable string, are refused.
	 *
	 * @return void
	 */
	public function testMalformedContextIsRefused(): void {
		$this->service->expects(self::never())->method('previewRules');
		$cases = [
			[['groups' => 'staff'], 'context.groups must be an array of group ids'],
			[['groups' => ['staff', 7]], 'context.groups must be an array of group ids'],
			[['datetime' => 1700000000], 'context.datetime must be a date/time string'],
			[['datetime' => 'not a date'], 'context.datetime is not a valid date/time'],
		];

		foreach ($cases as [$context, $message]) {
			$response = $this->controller()->preview([], $context);
			self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
			self::assertSame(['error' => $message], $response->getData());
		}
	}//end testMalformedContextIsRefused()

	/**
	 * Groups are re-indexed, the given moment is used, and the verdict is answered as is.
	 *
	 * @return void
	 */
	public function testTheContextIsPassedToThePreview(): void {
		$verdict = ['visible' => false, 'rules' => []];
		$this->service->expects(self::once())->method('previewRules')->with(
			[],
			'alice',
			['staff', 'admin'],
			self::callback(static fn (DateTime $now): bool => $now->format('Y-m-d H:i') === '2026-05-04 09:30')
		)->willReturn($verdict);

		$response = $this->controller()->preview(null, ['groups' => ['a' => 'staff', 'b' => 'admin'], 'datetime' => '2026-05-04 09:30']);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($verdict, $response->getData());
	}//end testTheContextIsPassedToThePreview()

	/**
	 * Without a moment the preview tests against now; a failing preview is a generic 400.
	 *
	 * @return void
	 */
	public function testAFailingPreviewIsAGenericRefusal(): void {
		$before = new DateTime();
		$this->service->expects(self::once())->method('previewRules')->with(
			[],
			'alice',
			[],
			self::callback(static fn (DateTime $now): bool => $now >= $before && $now <= new DateTime())
		)->willThrowException(new Exception('evaluator exploded'));

		$response = $this->controller()->preview(null, null);

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['error' => 'Preview failed'], $response->getData());
	}//end testAFailingPreviewIsAGenericRefusal()
}//end class
