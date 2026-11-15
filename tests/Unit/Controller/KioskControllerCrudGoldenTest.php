<?php

/**
 * KioskControllerCrudGoldenTest
 *
 * Golden record of the kiosk playlist create, list, update and revoke
 * endpoints: the sign-in check, the not-found / not-authorized / failure
 * answers, the logged failures and the service calls.
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

use OCA\LaunchPad\Controller\KioskController;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Service\KioskPlaylistDraft;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The playlist CRUD endpoints, case by case.
 */
class KioskControllerCrudGoldenTest extends TestCase {

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
			'create' => static fn (KioskController $c) => $c->create(name: 'Wall', entries: [['dashboardUuid' => 'd1', 'dwellSeconds' => 30]], refreshSeconds: 120),
			'create defaults' => static fn (KioskController $c) => $c->create(),
			'index' => static fn (KioskController $c) => $c->index(),
			'update' => static fn (KioskController $c) => $c->update(id: 7, name: 'Hall', entries: null, refreshSeconds: null),
			'destroy' => static fn (KioskController $c) => $c->destroy(id: 7),
		];
		$cases = [
			'anonymous' => [null, null],
			'ok' => ['alice', null],
			'not found' => ['alice', new PlaylistNotFoundException()],
			'not authorized' => ['alice', new OCSForbiddenException('no')],
			'crash' => ['alice', new RuntimeException('db down')],
		];

		$actual = [];
		foreach ($endpoints as $endpoint => $call) {
			foreach ($cases as $case => [$userId, $failure]) {
				if ($case === 'not found' && str_starts_with($endpoint, 'create') === true) {
					continue;
				}
				$this->trace = [];
				try {
					$response = $call($this->controller(userId: $userId, failure: $failure));
					$actual[$endpoint.' / '.$case] = [$response->getStatus(), $response->getData(), $this->trace];
				} catch (\Throwable $e) {
					$actual[$endpoint.' / '.$case] = ['thrown' => get_class($e), $this->trace];
				}
			}
		}

		$this->assertSame(json_decode((string) file_get_contents(__DIR__.'/fixtures/kiosk-crud.json'), true), $this->record(actual: $actual));
	}//end testEndpointsMatchTheRecording()

	/**
	 * Normalise and optionally record the outcomes.
	 *
	 * @param array<string, mixed> $actual The outcomes.
	 *
	 * @return array<string, mixed>
	 */
	private function record(array $actual): array {
		$actual = json_decode((string) json_encode($actual), true);
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(__DIR__.'/fixtures/kiosk-crud.json', json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		return $actual;
	}//end record()

	/**
	 * The controller over a recording service mock.
	 *
	 * @param string|null     $userId  The signed-in user.
	 * @param \Throwable|null $failure What every service call throws, or null to succeed.
	 *
	 * @return KioskController
	 */
	private function controller(?string $userId, ?\Throwable $failure): KioskController {
		$playlist = new KioskPlaylist();
		$playlist->setName('Wall');
		$playlist->setToken(str_repeat('a', 64));

		$service = $this->createMock(KioskService::class);
		$answer  = function (string $method, array $args) use ($failure, $playlist) {
			$this->trace[] = [$method, array_map(static fn ($arg) => ($arg instanceof KioskPlaylistDraft) ? [$arg->name, $arg->entries, $arg->refresh] : $arg, $args)];
			if ($failure !== null) {
				throw $failure;
			}
			return ($method === 'listPlaylists') ? [$playlist, $playlist] : $playlist;
		};
		$service->method('createPlaylist')->willReturnCallback(fn (...$args) => $answer('createPlaylist', $args));
		$service->method('listPlaylists')->willReturnCallback(fn (...$args) => $answer('listPlaylists', $args));
		$service->method('updatePlaylist')->willReturnCallback(fn (...$args) => $answer('updatePlaylist', $args));
		$service->method('revokePlaylist')->willReturnCallback(
			function (...$args) use ($answer): void {
				$answer('revokePlaylist', $args);
			}
		);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context): void {
				$this->trace[] = ['log', $message, $context];
			}
		);

		return new KioskController(
			request: $this->createMock(IRequest::class),
			kioskService: $service,
			shareContext: $this->createMock(PublicShareContext::class),
			logger: $logger,
			userId: $userId
		);
	}//end controller()
}//end class
