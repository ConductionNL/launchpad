<?php

/**
 * ActionMatrixController Test
 *
 * Pins the admin-only action matrix endpoints: both are gated by the
 * admin setting, the read merges the seed file's declared action keys
 * into the stored matrix, and the write tolerates a missing matrix and
 * reports an encoding failure as HTTP 400.
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

use JsonException;
use OCA\LaunchPad\Controller\ActionMatrixController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ActionMatrixControllerTest extends TestCase {
	/**
	 * Controller.
	 *
	 * @var ActionMatrixController
	 */
	private ActionMatrixController $controller;

	/**
	 * Request.
	 *
	 * @var IRequest
	 */
	private IRequest&MockObject $request;

	/**
	 * Action auth.
	 *
	 * @var ActionAuthService
	 */
	private ActionAuthService&MockObject $actionAuth;

	/**
	 * Group manager.
	 *
	 * @var IGroupManager
	 */
	private IGroupManager&MockObject $groupManager;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->actionAuth = $this->createMock(originalClassName: ActionAuthService::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);

		$this->controller = new ActionMatrixController(
			request: $this->request,
			actionAuth: $this->actionAuth,
			groupManager: $this->groupManager,
		);
	}//end setUp()

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function adminEndpoints(): array {
		return [
			'read'  => ['getMatrix'],
			'write' => ['setMatrix'],
		];
	}//end adminEndpoints()

	/**
	 * @dataProvider adminEndpoints
	 * @param string $method Method.
	 * @return void
	 */
	public function testEndpointIsGatedByTheAdminSetting(string $method): void {
		$attributes = (new ReflectionMethod(ActionMatrixController::class, $method))
			->getAttributes(AuthorizedAdminSetting::class);

		$this->assertCount(expectedCount: 1, haystack: $attributes);
		$this->assertSame(
			expected: [LaunchPadAdmin::class],
			actual: $attributes[0]->getArguments()
		);
	}//end testEndpointIsGatedByTheAdminSetting()

	/**
	 * Get matrix merges seed actions sorted and lists groups.
	 *
	 * @return void
	 */
	public function testGetMatrixMergesSeedActionsSortedAndListsGroups(): void {
		$this->actionAuth->method('getMatrix')->willReturn(value: [
			'zz.custom-action'    => ['editors'],
			'admin.get-my-role'   => ['@all'],
		]);
		$admin = $this->createMock(originalClassName: IGroup::class);
		$admin->method('getGID')->willReturn(value: 'admin');
		$editors = $this->createMock(originalClassName: IGroup::class);
		$editors->method('getGID')->willReturn(value: 'editors');
		$this->groupManager->method('search')->with('')->willReturn(value: [$admin, $editors]);

		$data = $this->controller->getMatrix()->getData();

		$seedKeys = array_keys(
			json_decode(
				(string)file_get_contents(__DIR__ . '/../../../lib/actions.seed.json'),
				associative: true,
				flags: JSON_THROW_ON_ERROR
			)['actions']
		);
		$expected = array_values(array_unique(array_merge(['zz.custom-action'], $seedKeys)));
		sort($expected);

		$this->assertSame(expected: $expected, actual: $data['actions']);
		// A key in both the matrix and the seed appears once.
		$this->assertSame(
			expected: 1,
			actual: count(array_keys($data['actions'], 'admin.get-my-role', true))
		);
		$this->assertSame(expected: ['admin', 'editors'], actual: $data['groups']);
		$this->assertSame(expected: ['editors'], actual: $data['matrix']['zz.custom-action']);
	}//end testGetMatrixMergesSeedActionsSortedAndListsGroups()

	/**
	 * Set matrix writes the submitted matrix and returns the stored one.
	 *
	 * @return void
	 */
	public function testSetMatrixWritesTheSubmittedMatrixAndReturnsTheStoredOne(): void {
		$submitted = ['dashboards.create' => ['staff']];
		$this->request->method('getParam')->with('matrix')->willReturn(value: $submitted);
		$this->actionAuth->expects($this->once())->method('setMatrix')->with($submitted);
		$this->actionAuth->method('getMatrix')->willReturn(value: $submitted);

		$response = $this->controller->setMatrix();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['matrix' => $submitted], actual: $response->getData());
	}//end testSetMatrixWritesTheSubmittedMatrixAndReturnsTheStoredOne()

	/**
	 * Set matrix treats anon array matrix as empty.
	 *
	 * @return void
	 */
	public function testSetMatrixTreatsANonArrayMatrixAsEmpty(): void {
		$this->request->method('getParam')->with('matrix')->willReturn(value: 'not-a-matrix');
		$this->actionAuth->expects($this->once())->method('setMatrix')->with([]);
		$this->actionAuth->method('getMatrix')->willReturn(value: []);

		$this->assertSame(
			expected: ['matrix' => []],
			actual: $this->controller->setMatrix()->getData()
		);
	}//end testSetMatrixTreatsANonArrayMatrixAsEmpty()

	/**
	 * Set matrix reports an encoding failure as bad request.
	 *
	 * @return void
	 */
	public function testSetMatrixReportsAnEncodingFailureAsBadRequest(): void {
		$this->request->method('getParam')->willReturn(value: ['a' => ['b']]);
		$this->actionAuth->method('setMatrix')
			->willThrowException(exception: new JsonException(message: 'Malformed UTF-8'));

		$response = $this->controller->setMatrix();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(
			expected: ['error' => 'Could not encode the action matrix: Malformed UTF-8'],
			actual: $response->getData()
		);
	}//end testSetMatrixReportsAnEncodingFailureAsBadRequest()
}//end class
