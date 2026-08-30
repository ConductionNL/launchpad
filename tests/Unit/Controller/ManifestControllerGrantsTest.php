<?php

/**
 * ManifestControllerGrantsTest
 *
 * Unit tests for the second dashboard source in ManifestController: objects
 * explicitly GRANTED to the caller through OpenRegister's per-object sharing
 * primitive, folded into the manifest alongside the ones they own.
 *
 * Before this, the controller's own docblock promised "objects the user owns OR
 * that list the user in `sharedWith`" — but `sharedWith` was declared in the
 * register descriptor, seeded as `[]` by a migration, and read by no code at
 * all. Only the `owner` filter ever ran, so a shared dashboard never appeared
 * in anybody's manifest.
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

use OCA\LaunchPad\Controller\ManifestController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for grant-sourced dashboards in the v2 manifest.
 */
class ManifestControllerGrantsTest extends TestCase {
	private IRequest&MockObject $request;

	private ContainerInterface&MockObject $container;

	private ActionAuthService&MockObject $actionAuth;

	private IUserSession&MockObject $userSession;

	private LoggerInterface&MockObject $logger;

	/**
	 * Set up the shared collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->actionAuth = $this->createMock(originalClassName: ActionAuthService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		$user = $this->createMock(originalClassName: IUser::class);
		$this->userSession->method('getUser')->willReturn($user);
	}//end setUp()

	/**
	 * Wire the container so ObjectService and the grant resolver resolve.
	 *
	 * OpenRegister is not on launchpad's unit-test autoload path, so both
	 * collaborators are anonymous doubles matched on the method names the
	 * controller actually calls. `$grantResolver === null` simulates an
	 * OpenRegister release that predates the sharing primitive.
	 *
	 * @param object $objectService The ObjectService double.
	 * @param object|null $grantResolver The grant-resolver double, or null to
	 *                                   make the container throw for it.
	 *
	 * @return void
	 */
	private function wireContainer(object $objectService, ?object $grantResolver): void {
		$this->container->method('get')->willReturnCallback(
			static function (string $id) use ($objectService, $grantResolver) {
				if ($id === 'OCA\OpenRegister\Service\ObjectService') {
					return $objectService;
				}

				if ($id === 'OCA\OpenRegister\Service\Rbac\ObjectGrantResolver') {
					if ($grantResolver === null) {
						throw new \RuntimeException('not registered');
					}

					return $grantResolver;
				}

				throw new \RuntimeException('unexpected service ' . $id);
			}
		);
	}//end wireContainer()

	/**
	 * An ObjectService double returning owned rows, then granted rows.
	 *
	 * It distinguishes the two calls the way OpenRegister actually does: the
	 * owned query carries a NESTED `@self` metadata filter, the granted query
	 * carries the top-level `ids` config key. Both spellings are load-bearing and
	 * both are asserted, because OpenRegister silently degrades the wrong one
	 * into a property filter that matches nothing — which is exactly how this
	 * endpoint shipped an always-empty manifest. Note that neither a bare
	 * `owner` nor a DOTTED `@self.owner` works; only the nested array does.
	 *
	 * @param array<int, array<string, mixed>> $owned Rows for the owner query.
	 * @param array<int, array<string, mixed>> $granted Rows for the ids query.
	 * @param array<int, array<string, mixed>> $seenConfigs Collected configs, by reference.
	 *
	 * @return object
	 */
	private function objectServiceDouble(array $owned, array $granted, array &$seenConfigs): object {
		return new class($owned, $granted, $seenConfigs) {
			/**
			 * @param array<int, array<string, mixed>> $owned Owner-query rows.
			 * @param array<int, array<string, mixed>> $granted Ids-query rows.
			 * @param array<int, array<string, mixed>> $seenConfigs Config log.
			 */
			public function __construct(
				private readonly array $owned,
				private readonly array $granted,
				private array &$seenConfigs,
			) {
			}

			/**
			 * @param array<string, mixed> $config The OR findAll config.
			 *
			 * @return array<int, array<string, mixed>>
			 */
			public function findAll(array $config): array {
				$this->seenConfigs[] = $config;

				if (isset($config['ids']) === true) {
					return $this->granted;
				}

				return $this->owned;
			}
		};
	}//end objectServiceDouble()

	/**
	 * A grant-resolver double returning a fixed uuid => bitmask map.
	 *
	 * @param array<int, string> $uuids The granted object uuids.
	 * @param array<int, string> $seenActions Collected actions, by reference.
	 *
	 * @return object
	 */
	private function grantResolverDouble(array $uuids, array &$seenActions): object {
		return new class($uuids, $seenActions) {
			/**
			 * @param array<int, string> $uuids Granted uuids.
			 * @param array<int, string> $seenActions Action log.
			 */
			public function __construct(
				private readonly array $uuids,
				private array &$seenActions,
			) {
			}

			/**
			 * @param string|null $userId The caller.
			 * @param string $action The verb asked about.
			 *
			 * @return array<string, int>
			 */
			public function grantedObjectUuidsFor(?string $userId, string $action): array {
				$this->seenActions[] = $action;

				return array_fill_keys($this->uuids, 1);
			}
		};
	}//end grantResolverDouble()

	/**
	 * Build the controller under test.
	 *
	 * @return ManifestController
	 */
	private function makeController(): ManifestController {
		return new ManifestController(
			request: $this->request,
			container: $this->container,
			actionAuth: $this->actionAuth,
			userSession: $this->userSession,
			logger: $this->logger,
			userId: 'alice'
		);
	}//end makeController()

	/**
	 * Extract the dashboard slugs from a manifest response body.
	 *
	 * buildManifest() does not echo the slug back as a field — it bakes it into
	 * the page `route` (`/<slug>`) and `id` (`dashboard-<slug>`). Reading `route`
	 * keeps this helper honest about the shape the endpoint actually emits.
	 *
	 * @param mixed $body The JSONResponse data.
	 *
	 * @return array<int, string>
	 */
	private function slugsOf(mixed $body): array {
		$slugs = [];
		foreach ((($body['pages'] ?? [])) as $page) {
			if (isset($page['route']) === true) {
				$slugs[] = ltrim($page['route'], '/');
			}
		}

		sort($slugs);

		return $slugs;
	}//end slugsOf()

	/**
	 * A granted dashboard appears in the manifest alongside an owned one.
	 *
	 * @return void
	 */
	public function testAGrantedDashboardIsIncluded(): void {
		$seenConfigs = [];
		$seenActions = [];

		$this->wireContainer(
			objectService: $this->objectServiceDouble(
				owned: [['id' => 'o1', 'slug' => 'mine', 'title' => 'Mine']],
				granted: [['id' => 'g1', 'slug' => 'shared', 'title' => 'Shared']],
				seenConfigs: $seenConfigs
			),
			grantResolver: $this->grantResolverDouble(uuids: ['g1'], seenActions: $seenActions)
		);

		$response = $this->makeController()->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['mine', 'shared'], $this->slugsOf($response->getData()));

		// The verb must be `read` — appearing in a manifest is a read, and the
		// resolver refuses anything outside the five core verbs.
		$this->assertSame(['read'], $seenActions);

		// And the two queries really were two different queries: one scoped by
		// owner, one by id. A controller that issued the owner query twice would
		// still have produced the page assertion above.
		//
		// The exact spellings are pinned on purpose. OpenRegister routes
		// `@self.owner` to the `_owner` metadata column, but a bare `owner` to a
		// *property* filter — and a property the dashboard schema does not have
		// matches nothing, which is precisely how this endpoint returned an empty
		// manifest to every user including the owner. Likewise `ids` is a
		// first-class config key matching `_uuid`/`_slug`, whereas
		// `filters['uuid']` would degrade the same silent way.
		$this->assertSame('alice', $seenConfigs[0]['filters']['@self']['owner']);
		$this->assertArrayNotHasKey('owner', $seenConfigs[0]['filters']);
		$this->assertArrayNotHasKey('@self.owner', $seenConfigs[0]['filters']);
		$this->assertArrayNotHasKey('ids', $seenConfigs[0]);

		$this->assertSame(['g1'], $seenConfigs[1]['ids']);
		$this->assertArrayNotHasKey('uuid', $seenConfigs[1]['filters']);
	}//end testAGrantedDashboardIsIncluded()

	/**
	 * A dashboard both owned and granted appears exactly once.
	 *
	 * @return void
	 */
	public function testAnOwnedAndGrantedDashboardIsNotDuplicated(): void {
		$seenConfigs = [];
		$seenActions = [];

		$row = ['id' => 'same', 'slug' => 'both', 'title' => 'Both'];

		$this->wireContainer(
			objectService: $this->objectServiceDouble(
				owned: [$row],
				granted: [$row],
				seenConfigs: $seenConfigs
			),
			grantResolver: $this->grantResolverDouble(uuids: ['same'], seenActions: $seenActions)
		);

		$response = $this->makeController()->index();

		$this->assertSame(['both'], $this->slugsOf($response->getData()));
	}//end testAnOwnedAndGrantedDashboardIsNotDuplicated()

	/**
	 * Without the sharing primitive the manifest degrades to owned-only.
	 *
	 * This is the fail-soft path: an OpenRegister release predating per-object
	 * grants must not break the manifest, it must simply return what it always
	 * returned.
	 *
	 * The control is the test above: with the resolver present, the same fixture
	 * yields two pages. So one page here means the absence of the resolver is
	 * what dropped it — not a fixture that never had a granted row.
	 *
	 * @return void
	 */
	public function testWithoutTheGrantResolverTheManifestIsOwnedOnly(): void {
		$seenConfigs = [];

		$this->wireContainer(
			objectService: $this->objectServiceDouble(
				owned: [['id' => 'o1', 'slug' => 'mine', 'title' => 'Mine']],
				granted: [['id' => 'g1', 'slug' => 'shared', 'title' => 'Shared']],
				seenConfigs: $seenConfigs
			),
			grantResolver: null
		);

		$response = $this->makeController()->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['mine'], $this->slugsOf($response->getData()));

		// The uuid query was never issued at all.
		$this->assertCount(1, $seenConfigs);
	}//end testWithoutTheGrantResolverTheManifestIsOwnedOnly()

	/**
	 * No grants means no second query, not an empty IN() clause.
	 *
	 * @return void
	 */
	public function testNoGrantsIssuesNoSecondQuery(): void {
		$seenConfigs = [];
		$seenActions = [];

		$this->wireContainer(
			objectService: $this->objectServiceDouble(
				owned: [['id' => 'o1', 'slug' => 'mine', 'title' => 'Mine']],
				granted: [],
				seenConfigs: $seenConfigs
			),
			grantResolver: $this->grantResolverDouble(uuids: [], seenActions: $seenActions)
		);

		$response = $this->makeController()->index();

		$this->assertSame(['mine'], $this->slugsOf($response->getData()));
		$this->assertCount(1, $seenConfigs);
	}//end testNoGrantsIssuesNoSecondQuery()

	/**
	 * Exceeding the cap truncates AND warns — it is never silent.
	 *
	 * A truncated manifest that logged nothing would be indistinguishable from
	 * "nothing is shared with this user", which is the failure mode worth
	 * guarding against.
	 *
	 * @return void
	 */
	public function testExceedingTheGrantCapLogsAWarning(): void {
		$seenConfigs = [];
		$seenActions = [];

		$uuids = [];
		for ($i = 0; $i < 250; $i++) {
			$uuids[] = 'u' . $i;
		}

		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('exceeds the 200 cap'), $this->anything());

		$this->wireContainer(
			objectService: $this->objectServiceDouble(
				owned: [],
				granted: [],
				seenConfigs: $seenConfigs
			),
			grantResolver: $this->grantResolverDouble(uuids: $uuids, seenActions: $seenActions)
		);

		$this->makeController()->index();

		// Truncated to the cap, not sent whole.
		$this->assertCount(200, $seenConfigs[1]['ids']);
	}//end testExceedingTheGrantCapLogsAWarning()
}//end class
