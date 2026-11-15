<?php

/**
 * ManifestControllerCoverageTest
 *
 * Unit tests for the degradation paths of ManifestController: OpenRegister
 * absent, register/schema not provisioned, OR API errors on either source,
 * the result-shape normalisation in extractData(), the granted-dashboard cap
 * and the rows buildManifest() skips.
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

use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\ManifestController;
use OCA\LaunchPad\Controller\ManifestTools;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\LaunchPad\Controller\ManifestController
 * @uses   \OCA\LaunchPad\Controller\ManifestTools
 * @uses   \OCA\LaunchPad\Controller\ActionCaller
 */
class ManifestControllerCoverageTest extends TestCase {
	private ContainerInterface&MockObject $container;

	private LoggerInterface&MockObject $logger;

	private IUserSession&MockObject $userSession;

	/**
	 * Set up the shared collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->container = $this->createMock(ContainerInterface::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn($this->createMock(IUser::class));
	}//end setUp()

	/**
	 * Build the controller under test.
	 *
	 * @return ManifestController
	 */
	private function makeController(): ManifestController {
		return new ManifestController(
			request: $this->createMock(IRequest::class),
			tools: new ManifestTools(container: $this->container, logger: $this->logger),
			caller: new ActionCaller(
				actionAuth: $this->createMock(ActionAuthService::class),
				userSession: $this->userSession
			),
			userId: 'alice'
		);
	}//end makeController()

	/**
	 * Wire the container to the given doubles; a null resolver is "not registered".
	 *
	 * @param object      $objectService The ObjectService double.
	 * @param object|null $grantResolver The grant-resolver double, or null.
	 *
	 * @return void
	 */
	private function wire(object $objectService, ?object $grantResolver): void {
		$this->container->method('get')->willReturnCallback(
			static function (string $id) use ($objectService, $grantResolver) {
				if ($id === 'OCA\OpenRegister\Service\ObjectService') {
					return $objectService;
				}

				if ($grantResolver === null) {
					throw new \RuntimeException('not registered');
				}

				return $grantResolver;
			}
		);
	}//end wire()

	/**
	 * An ObjectService double whose owned and granted answers are closures.
	 *
	 * @param \Closure $owned   Answers the owner query.
	 * @param \Closure $granted Answers the ids query; receives the config.
	 *
	 * @return object
	 */
	private function objectService(\Closure $owned, \Closure $granted): object {
		return new class($owned, $granted) {
			/**
			 * @param \Closure $owned   Owner-query answer.
			 * @param \Closure $granted Ids-query answer.
			 */
			public function __construct(
				private readonly \Closure $owned,
				private readonly \Closure $granted,
			) {
			}

			/**
			 * @param array<string, mixed> $config The findAll config.
			 *
			 * @return mixed
			 */
			public function findAll(array $config): mixed {
				if (isset($config['ids']) === true) {
					return ($this->granted)($config);
				}

				return ($this->owned)($config);
			}
		};
	}//end objectService()

	/**
	 * A grant-resolver double returning the given uuids.
	 *
	 * @param array<int, string> $uuids Granted uuids.
	 *
	 * @return object
	 */
	private function resolver(array $uuids): object {
		return new class($uuids) {
			/**
			 * @param array<int, string> $uuids Granted uuids.
			 */
			public function __construct(private readonly array $uuids) {
			}

			/**
			 * @param string|null $userId The caller.
			 * @param string      $action The verb.
			 *
			 * @return array<string, int>
			 */
			public function grantedObjectUuidsFor(?string $userId, string $action): array {
				return array_fill_keys($this->uuids, 1);
			}
		};
	}//end resolver()

	/**
	 * Page routes in the response body, in order.
	 *
	 * @param mixed $body The response data.
	 *
	 * @return array<int, string>
	 */
	private function routes(mixed $body): array {
		return array_map(static fn (array $page): string => $page['route'], $body['pages']);
	}//end routes()

	public function testAMissingObjectServiceYieldsAnEmptyManifest(): void {
		$this->container->method('get')->willThrowException(new \RuntimeException('app disabled'));
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('ObjectService unavailable — returning empty manifest. app disabled'));

		$response = $this->makeController()->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$body = $response->getData();
		$this->assertSame([], $body['pages']);
		$this->assertSame([], $body['menu']);
		$this->assertSame(['openregister'], $body['dependencies']);
		$this->assertSame('1.0.0', $body['version']);
		$this->assertSame(['user' => ['id' => 'alice']], $body['runtime']);
	}

	public function testAnUnprovisionedRegisterIsLoggedAsInfoAndYieldsNoPages(): void {
		$this->wire(
			objectService: $this->objectService(
				owned: static fn () => throw new DoesNotExistException('no register'),
				granted: static fn () => []
			),
			grantResolver: null
		);
		$this->logger->expects($this->once())
			->method('info')
			->with(
				$this->stringContains('not provisioned — returning empty manifest. no register'),
				$this->equalTo(['app' => 'launchpad', 'userId' => 'alice'])
			);
		$this->logger->expects($this->never())->method('error');

		$response = $this->makeController()->index();

		$this->assertSame([], $response->getData()['pages']);
	}

	public function testAnOwnedQueryFailureIsLoggedAndGrantsStillApply(): void {
		$this->wire(
			objectService: $this->objectService(
				owned: static fn () => throw new \InvalidArgumentException('bad filter'),
				granted: static fn () => [['id' => 'g1', 'slug' => 'shared', 'title' => 'Shared']]
			),
			grantResolver: $this->resolver(['g1'])
		);
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('failed to fetch dashboards from OpenRegister: bad filter'));

		$response = $this->makeController()->index();

		$this->assertSame(['/shared'], $this->routes($response->getData()));
	}

	public function testResultItemsAreNormalisedAndUnusableOnesSkipped(): void {
		$getObject = new class {
			/**
			 * @return array<string, string>
			 */
			public function getObject(): array {
				return ['id' => 'a', 'slug' => 'alpha', 'title' => 'Alpha'];
			}
		};
		$badGetObject = new class {
			/**
			 * @return string
			 */
			public function getObject(): string {
				return 'not an array';
			}
		};
		$serializable = new class implements \JsonSerializable {
			/**
			 * @return array<string, string>
			 */
			public function jsonSerialize(): array {
				return ['id' => 'b', 'slug' => 'beta', 'title' => 'Beta'];
			}
		};
		$badSerializable = new class implements \JsonSerializable {
			/**
			 * @return string
			 */
			public function jsonSerialize(): string {
				return 'scalar';
			}
		};

		$owned = [
			$getObject,
			$badGetObject,
			$serializable,
			$badSerializable,
			'just a string',
			[],
			['id' => 'a', 'slug' => 'duplicate', 'title' => 'Dup'],
			['id' => 'c', 'slug' => 'no-title'],
			['id' => 'd', 'title' => 'No slug'],
		];
		$this->wire(
			objectService: $this->objectService(owned: static fn () => $owned, granted: static fn () => []),
			grantResolver: null
		);

		$body = $this->makeController()->index()->getData();

		$this->assertSame(['/alpha', '/beta'], $this->routes($body));
		$this->assertSame(
			[
				[
					'id' => 'menu-alpha',
					'label' => 'Alpha',
					'route' => 'dashboard-alpha',
					'order' => 0,
					'icon' => 'ViewDashboardOutline',
				],
				[
					'id' => 'menu-beta',
					'label' => 'Beta',
					'route' => 'dashboard-beta',
					'order' => 1,
					'icon' => 'ViewDashboardOutline',
				],
			],
			$body['menu']
		);
		$this->assertSame(
			['id' => 'dashboard-alpha', 'route' => '/alpha', 'type' => 'dashboard', 'title' => 'Alpha', 'widgets' => []],
			$body['pages'][0]
		);
	}

	public function testNonArrayResultsFromBothSourcesYieldNoPages(): void {
		$this->wire(
			objectService: $this->objectService(owned: static fn () => null, granted: static fn () => 'oops'),
			grantResolver: $this->resolver(['g1'])
		);

		$this->assertSame([], $this->makeController()->index()->getData()['pages']);
	}

	public function testEmptyGrantedItemsAreDropped(): void {
		$this->wire(
			objectService: $this->objectService(
				owned: static fn () => [],
				granted: static fn () => [[], ['id' => 'g2', 'slug' => 'kept', 'title' => 'Kept'], 42]
			),
			grantResolver: $this->resolver(['g1', 'g2', 'g3'])
		);

		$this->assertSame(['/kept'], $this->routes($this->makeController()->index()->getData()));
	}

	public function testAnUnprovisionedRegisterOnTheGrantQueryKeepsOwnedDashboards(): void {
		$this->wire(
			objectService: $this->objectService(
				owned: static fn () => [['id' => 'o1', 'slug' => 'mine', 'title' => 'Mine']],
				granted: static fn () => throw new DoesNotExistException('gone')
			),
			grantResolver: $this->resolver(['g1'])
		);
		$this->logger->expects($this->never())->method('error');

		$this->assertSame(['/mine'], $this->routes($this->makeController()->index()->getData()));
	}

	public function testAGrantQueryFailureIsLoggedAndOwnedDashboardsKept(): void {
		$this->wire(
			objectService: $this->objectService(
				owned: static fn () => [['id' => 'o1', 'slug' => 'mine', 'title' => 'Mine']],
				granted: static fn () => throw new \RuntimeException('db down')
			),
			grantResolver: $this->resolver(['g1'])
		);
		$this->logger->expects($this->once())
			->method('error')
			->with(
				$this->stringContains('failed to fetch granted dashboards from OpenRegister: db down'),
				$this->equalTo(['app' => 'launchpad', 'userId' => 'alice'])
			);

		$this->assertSame(['/mine'], $this->routes($this->makeController()->index()->getData()));
	}

	public function testGrantsBeyondTheCapAreTruncatedWithAWarning(): void {
		$uuids = [];
		for ($i = 0; $i < 205; $i++) {
			$uuids[] = 'uuid-' . $i;
		}

		$seenIds = null;
		$this->wire(
			objectService: $this->objectService(
				owned: static fn () => [],
				granted: static function (array $config) use (&$seenIds): array {
					$seenIds = $config['ids'];
					return [];
				}
			),
			grantResolver: $this->resolver($uuids)
		);
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('205 granted dashboards exceeds the 200 cap — manifest truncated.'));

		$this->makeController()->index();

		$this->assertSame(array_slice($uuids, 0, 200), $seenIds);
	}

	public function testAnAnonymousCallerIsRefusedBeforeOpenRegisterIsTouched(): void {
		$this->container->expects($this->never())->method('get');

		$controller = new ManifestController(
			request: $this->createMock(IRequest::class),
			tools: new ManifestTools(container: $this->container, logger: $this->logger),
			caller: new ActionCaller(
				actionAuth: $this->createMock(ActionAuthService::class),
				userSession: $this->userSession
			),
			userId: null
		);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $controller->index()->getStatus());
	}
}//end class
