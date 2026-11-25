<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The runtime manifest kept per user and app version, the page's first
 * load from it, and the endpoint answering from it.
 *
 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-4
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\ManifestCache;
use OCA\LaunchPad\Controller\ManifestController;
use OCA\LaunchPad\Controller\ManifestFirstLoad;
use OCA\LaunchPad\Controller\ManifestTools;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\App\IAppManager;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Tests for {@see ManifestCache} and {@see ManifestFirstLoad}.
 */
class ManifestCacheTest extends TestCase {
	/** @var array<string, mixed> What the fake cache holds. */
	private array $stored = [];

	/** @var ActionAuthService&MockObject */
	private ActionAuthService $actionAuth;

	/** @var IUserSession&MockObject */
	private IUserSession $userSession;

	/**
	 * A cache factory over an in-memory cache, and the action check.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->actionAuth = $this->createMock(originalClassName: ActionAuthService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->userSession->method('getUser')->willReturn($this->createMock(originalClassName: IUser::class));
	}//end setUp()

	/**
	 * The cache under test, on an in-memory store, for app version 1.2.3.
	 *
	 * @param bool $available Whether the server has a cache.
	 *
	 * @return ManifestCache The cache.
	 */
	private function cache(bool $available=true): ManifestCache {
		$store = $this->createMock(originalClassName: ICache::class);
		$store->method('get')->willReturnCallback(fn (string $key): mixed => ($this->stored[$key] ?? null));
		$store->method('set')->willReturnCallback(function (string $key, mixed $value, int $ttl): bool {
			$this->stored[$key] = $value;
			$this->stored['ttl:'.$key] = $ttl;
			return true;
		});
		$factory = $this->createMock(originalClassName: ICacheFactory::class);
		$factory->method('isAvailable')->willReturn($available);
		$factory->method('createDistributed')->willReturn($store);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppVersion')->willReturn('1.2.3');

		return new ManifestCache(cacheFactory: $factory, appManager: $apps);
	}//end cache()

	/**
	 * A kept manifest comes back for its user, for a minute, keyed by the app version.
	 *
	 * @return void
	 */
	public function testAKeptManifestComesBackForItsUser(): void {
		$cache = $this->cache();
		$manifest = ['$schema' => 'x', 'pages' => []];

		$cache->set(userId: 'pieter', manifest: $manifest);

		self::assertSame(expected: $manifest, actual: $cache->get(userId: 'pieter'));
		self::assertNull(actual: $cache->get(userId: 'alice'));
		self::assertSame(expected: ManifestCache::TTL, actual: $this->stored['ttl:pieter:1.2.3']);
	}//end testAKeptManifestComesBackForItsUser()

	/**
	 * Without a server cache, or for a value that is not a manifest, nothing comes back.
	 *
	 * @return void
	 */
	public function testNothingComesBackWithoutACacheOrAManifest(): void {
		$none = $this->cache(available: false);
		$none->set(userId: 'pieter', manifest: ['$schema' => 'x']);
		self::assertNull(actual: $none->get(userId: 'pieter'));

		$this->stored['pieter:1.2.3'] = ['pages' => []];
		self::assertNull(actual: $this->cache()->get(userId: 'pieter'));
	}//end testNothingComesBackWithoutACacheOrAManifest()

	/**
	 * The page carries the kept manifest only for a caller who may read it.
	 *
	 * @return void
	 */
	public function testThePageCarriesTheKeptManifestOnlyWhenAllowed(): void {
		$cache = $this->cache();
		$cache->set(userId: 'pieter', manifest: ['$schema' => 'x']);
		$actor = new RequestActor(userSession: $this->userSession, actionAuth: $this->actionAuth);

		self::assertSame(expected: ['$schema' => 'x'], actual: (new ManifestFirstLoad(cache: $cache, actor: $actor))->firstLoad(userId: 'pieter'));
		self::assertNull(actual: (new ManifestFirstLoad(cache: $cache))->firstLoad(userId: 'pieter'));
		self::assertNull(actual: (new ManifestFirstLoad(cache: $cache, actor: $actor))->firstLoad(userId: ''));

		$this->actionAuth->method('requireAction')->willThrowException(new OCSForbiddenException());
		self::assertNull(actual: (new ManifestFirstLoad(cache: $cache, actor: $actor))->firstLoad(userId: 'pieter'));
	}//end testThePageCarriesTheKeptManifestOnlyWhenAllowed()

	/**
	 * The endpoint answers from the kept manifest without reading OpenRegister.
	 *
	 * @return void
	 */
	public function testTheEndpointAnswersFromTheKeptManifest(): void {
		$cache = $this->cache();
		$cache->set(userId: 'pieter', manifest: ['$schema' => 'kept']);
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects(self::never())->method('get');

		$controller = new ManifestController(
			request: $this->createMock(originalClassName: IRequest::class),
			tools: new ManifestTools(container: $container, logger: new NullLogger(), cache: $cache),
			caller: new ActionCaller(actionAuth: $this->actionAuth, userSession: $this->userSession),
			userId: 'pieter'
		);

		self::assertSame(expected: ['$schema' => 'kept'], actual: $controller->index()->getData());
	}//end testTheEndpointAnswersFromTheKeptManifest()

	/**
	 * A manifest the endpoint builds is kept for the next load.
	 *
	 * @return void
	 */
	public function testABuiltManifestIsKept(): void {
		$cache = $this->cache();
		$objectService = new class {
			/**
			 * No dashboards.
			 *
			 * @param array $config The query.
			 *
			 * @return array The results.
			 */
			public function findAll(array $config): array {
				return [];
			}//end findAll()
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(static function (string $id) use ($objectService): object {
			if ($id === 'OCA\OpenRegister\Service\ObjectService') {
				return $objectService;
			}

			throw new \RuntimeException('not registered');
		});

		$controller = new ManifestController(
			request: $this->createMock(originalClassName: IRequest::class),
			tools: new ManifestTools(container: $container, logger: new NullLogger(), cache: $cache),
			caller: new ActionCaller(actionAuth: $this->actionAuth, userSession: $this->userSession),
			userId: 'pieter'
		);
		$built = $controller->index()->getData();

		self::assertArrayHasKey(key: '$schema', array: $built);
		self::assertSame(expected: $built, actual: $cache->get(userId: 'pieter'));
	}//end testABuiltManifestIsKept()
}//end class
