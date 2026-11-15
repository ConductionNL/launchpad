<?php

/**
 * PublicShareControllerCrudGoldenTest
 *
 * Golden record of the public share create, list and revoke endpoints:
 * the sign-in check, the not-found / not-authorized / failure answers,
 * the logged failures and the service calls.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PublicShareController;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The public share owner endpoints, case by case.
 */
class PublicShareControllerCrudGoldenTest extends TestCase {

	/**
	 * Service calls and log lines of the current call.
	 *
	 * @var array<int, mixed>
	 */
	private array $trace = [];

	/**
	 * Every endpoint × case answers, calls and logs as recorded.
	 *
	 * @return void
	 */
	public function testEndpointsMatchTheRecording(): void {
		$endpoints = [
			'create' => static fn (PublicShareController $c) => $c->create(uuid: 'd-1', password: 'pw', expiresAt: '2030-01-01'),
			'index' => static fn (PublicShareController $c) => $c->index(uuid: 'd-1'),
			'destroy' => static fn (PublicShareController $c) => $c->destroy(uuid: 'd-1', id: 7),
		];
		$cases = [
			'anonymous' => [null, null],
			'ok' => ['alice', null],
			'unknown dashboard' => ['alice', new DoesNotExistException('no')],
			'not authorized' => ['alice', new OCSForbiddenException('no')],
			'crash' => ['alice', new RuntimeException('db down')],
		];

		$actual = [];
		foreach ($endpoints as $endpoint => $call) {
			foreach ($cases as $case => [$userId, $failure]) {
				$this->trace = [];
				$response    = $call($this->controller(userId: $userId, failure: $failure));
				$actual[$endpoint.' / '.$case] = [$response->getStatus(), $response->getData(), $this->trace];
			}
		}

		$this->trace = [];
		$response    = $this->controller(userId: 'alice', failure: new ShareNotFoundException())->destroy(uuid: 'd-1', id: 7);
		$actual['destroy / unknown share'] = [$response->getStatus(), $response->getData(), $this->trace];

		$actual = json_decode((string) json_encode($actual), true);
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(__DIR__.'/fixtures/public-share-crud.json', json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(json_decode((string) file_get_contents(__DIR__.'/fixtures/public-share-crud.json'), true), $actual);
	}//end testEndpointsMatchTheRecording()

	/**
	 * The public render takes the header password when the query has none,
	 * marks the bearer and strips the creator from the share.
	 *
	 * @return void
	 */
	public function testShowUsesTheHeaderPasswordAndHidesTheCreator(): void {
		$cases = [];
		foreach (['hdr', ''] as $header) {
			$share = new PublicShare();
			$share->setToken('tok');
			$share->setCreatedBy('alice');
			$dashboard = new \OCA\LaunchPad\Db\Dashboard();
			$dashboard->setName('Board');
			$placement = new \OCA\LaunchPad\Db\WidgetPlacement();
			$placement->setWidgetId('text');

			$seen    = [];
			$service = $this->createMock(PublicShareService::class);
			$service->method('renderShareContent')->willReturnCallback(
				function (string $token, string $ipAddress, ?string $password) use (&$seen, $share, $dashboard, $placement): array {
					$seen[] = [$token, $ipAddress, $password];
					return ['share' => $share, 'dashboard' => $dashboard, 'placements' => [$placement]];
				}
			);
			$context = $this->createMock(PublicShareContext::class);
			$context->method('markBearer')->willReturnCallback(
				function (string $token) use (&$seen): void {
					$seen[] = ['bearer', $token];
				}
			);
			$request = $this->createMock(IRequest::class);
			$request->method('getRemoteAddress')->willReturn('10.0.0.1');
			$request->method('getHeader')->willReturnCallback(static fn (string $name): string => ($name === 'X-Share-Password') ? $header : '');
			$request->method('getParam')->willReturn('query');

			$controller = new PublicShareController(request: $request, shareService: $service, shareContext: $context, logger: $this->createMock(LoggerInterface::class), userId: null);
			$data       = $controller->show(token: 'tok')->getData();
			$cases[]    = [$seen, array_key_exists('createdBy', $data['share']), $data['dashboard']['name'], count($data['placements'])];
		}

		$this->assertSame(
			[
				[[['tok', '10.0.0.1', 'hdr'], ['bearer', 'tok']], false, 'Board', 1],
				[[['tok', '10.0.0.1', null], ['bearer', 'tok']], false, 'Board', 1],
			],
			$cases
		);
	}//end testShowUsesTheHeaderPasswordAndHidesTheCreator()

	/**
	 * The share password is never taken from the query string.
	 *
	 * @return void
	 */
	public function testShowTakesNoPasswordFromTheQuery(): void {
		$parameters = (new \ReflectionMethod(PublicShareController::class, 'show'))->getParameters();

		$this->assertSame(['token'], array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $parameters));
	}//end testShowTakesNoPasswordFromTheQuery()

	/**
	 * The controller over a recording service mock.
	 *
	 * @param string|null     $userId  The signed-in user.
	 * @param \Throwable|null $failure What every service call throws, or null to succeed.
	 *
	 * @return PublicShareController
	 */
	private function controller(?string $userId, ?\Throwable $failure): PublicShareController {
		$share = new PublicShare();
		$share->setDashboardUuid('d-1');
		$share->setToken(str_repeat('b', 64));

		$service = $this->createMock(PublicShareService::class);
		$answer  = function (string $method, array $args) use ($failure, $share) {
			$this->trace[] = [$method, $args];
			if ($failure !== null) {
				throw $failure;
			}
			return ($method === 'listActiveShares') ? [$share, $share] : $share;
		};
		$service->method('createPublicShare')->willReturnCallback(fn (...$args) => $answer('createPublicShare', $args));
		$service->method('listActiveShares')->willReturnCallback(fn (...$args) => $answer('listActiveShares', $args));
		$service->method('revokeShare')->willReturnCallback(
			function (...$args) use ($answer): void {
				$answer('revokeShare', $args);
			}
		);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message, array $context): void {
				$this->trace[] = ['log', $message, $context['exceptionClass']];
			}
		);

		return new PublicShareController(
			request: $this->createMock(IRequest::class),
			shareService: $service,
			shareContext: $this->createMock(PublicShareContext::class),
			logger: $logger,
			userId: $userId
		);
	}//end controller()
}//end class
