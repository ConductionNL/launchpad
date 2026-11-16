<?php

/**
 * TenantThemeApiController unit tests.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\TenantThemeApiController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\Embed\TenantThemeService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TenantThemeApiController::class)]
#[UsesClass(RequestGuard::class)]
class TenantThemeApiControllerTest extends TestCase {

	private TenantThemeService&MockObject $themes;

	/**
	 * A controller for a signed-in user, admin or not, with request params.
	 *
	 * @param bool                 $admin  Whether the user is an admin.
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return TenantThemeApiController
	 */
	private function controller(bool $admin=true, array $params=[]): TenantThemeApiController {
		$this->themes = $this->createMock(TenantThemeService::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn($admin);

		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn($params);

		return new TenantThemeApiController(
			$request,
			$this->themes,
			new RequestGuard(userSession: $session, groupManager: $groups, actionAuth: $this->createMock(ActionAuthService::class))
		);
	}//end controller()

	/**
	 * A non-admin is refused before any theme is touched.
	 *
	 * @return void
	 */
	public function testANonAdminIsRefused(): void {
		$controller = $this->controller(admin: false);
		$this->themes->expects($this->never())->method($this->anything());

		$this->assertSame(403, $controller->index()->getStatus());
		$this->assertSame(403, $controller->create()->getStatus());
		$this->assertSame(403, $controller->update(id: 'th-1')->getStatus());
		$this->assertSame(403, $controller->destroy(id: 'th-1', force: true)->getStatus());
	}//end testANonAdminIsRefused()

	/**
	 * The list is wrapped in results; create passes only the theme fields and
	 * the admin, answering 201 or 400 for a refusal.
	 *
	 * @return void
	 */
	public function testIndexAndCreate(): void {
		$controller = $this->controller(params: ['name' => 'Zeist', 'cssVariables' => ['--c-primary' => '#0b5394'], 'createdBy' => 'forged', '_route' => 'x']);
		$this->themes->method('listThemes')->willReturn([['id' => 'th-1', 'name' => 'Zeist', 'tokenCount' => 2]]);
		$this->themes->expects($this->exactly(2))->method('createTheme')
			->with(['name' => 'Zeist', 'cssVariables' => ['--c-primary' => '#0b5394']], 'admin')
			->willReturnOnConsecutiveCalls(['id' => 'th-2', 'name' => 'Zeist'], ['status' => 'error', 'error' => 'insufficient_contrast']);

		$this->assertSame(['results' => [['id' => 'th-1', 'name' => 'Zeist', 'tokenCount' => 2]]], $controller->index()->getData());
		$created = $controller->create();
		$this->assertSame([201, ['id' => 'th-2', 'name' => 'Zeist']], [$created->getStatus(), $created->getData()]);
		$refused = $controller->create();
		$this->assertSame([400, 'insufficient_contrast'], [$refused->getStatus(), $refused->getData()['error']]);
	}//end testIndexAndCreate()

	/**
	 * Update answers 200, 400 for a refusal and 404 for an unknown theme.
	 *
	 * @return void
	 */
	public function testUpdate(): void {
		$controller = $this->controller(params: ['name' => 'Zeist (nieuw)']);
		$this->themes->method('updateTheme')->willReturnMap(
			[
				['th-1', ['name' => 'Zeist (nieuw)'], ['id' => 'th-1', 'name' => 'Zeist (nieuw)']],
				['th-2', ['name' => 'Zeist (nieuw)'], ['status' => 'error', 'error' => 'invalid_customCss']],
				['gone', ['name' => 'Zeist (nieuw)'], null],
			]
		);

		$this->assertSame([200, 'Zeist (nieuw)'], [$controller->update(id: 'th-1')->getStatus(), $controller->update(id: 'th-1')->getData()['name']]);
		$this->assertSame(400, $controller->update(id: 'th-2')->getStatus());
		$this->assertSame(404, $controller->update(id: 'gone')->getStatus());
	}//end testUpdate()

	/**
	 * Delete passes force through: 409 while tokens use the theme, 200 once
	 * deleted, 404 for an unknown theme.
	 *
	 * @return void
	 */
	public function testDestroy(): void {
		$controller = $this->controller();
		$this->themes->method('deleteTheme')->willReturnMap(
			[
				['th-1', false, ['status' => 'error', 'error' => 'theme_in_use', 'tokenCount' => 2]],
				['th-1', true, ['deleted' => true, 'tokenCount' => 2]],
				['gone', false, null],
			]
		);

		$inUse = $controller->destroy(id: 'th-1');
		$this->assertSame([409, 2], [$inUse->getStatus(), $inUse->getData()['tokenCount']]);
		$this->assertSame([200, ['deleted' => true, 'tokenCount' => 2]], [$controller->destroy(id: 'th-1', force: true)->getStatus(), $controller->destroy(id: 'th-1', force: true)->getData()]);
		$this->assertSame(404, $controller->destroy(id: 'gone')->getStatus());
	}//end testDestroy()

	/**
	 * Without OpenRegister every action answers 503.
	 *
	 * @return void
	 */
	public function testUnavailableIs503(): void {
		$controller = $this->controller();
		$this->themes->method('listThemes')->willThrowException(new RuntimeException('Embeds need OpenRegister, which is not available.'));

		$response = $controller->index();
		$this->assertSame(
			[503, ['error' => 'unavailable', 'message' => 'Embeds need OpenRegister, which is not available.']],
			[$response->getStatus(), $response->getData()]
		);
	}//end testUnavailableIs503()
}//end class
