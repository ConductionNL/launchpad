<?php

/**
 * FleetAppIdTest
 *
 * Covers the fleet-rename resolver that every cross-app binding in this app
 * now goes through.
 *
 * These are not incidental branches. A cross-app lookup fails SILENTLY in
 * every direction that matters: `isInstalled()` on a name the instance never
 * registered returns false rather than raising, `class_exists()` on a moved
 * namespace answers false, and `ContainerInterface::get()` throws into a
 * caller that is already catching. So the paths exercised here — old id only,
 * neither id, an app manager that throws, a container that throws — are the
 * ones that decide whether an integration works on a half-migrated instance,
 * and none of them announces itself when it goes wrong.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Support\FleetAppId;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

// Declares a class under a PRE-RENAME fleet namespace. Not autoloadable:
// composer's autoload-dev maps only `Unit\` onto tests/, and the whole point
// of the fixture is to live outside that root.
require_once __DIR__ . '/FleetAppIdFixtures.php';

#[Small]
class FleetAppIdTest extends TestCase {

	/**
	 * An app manager where exactly the named ids are installed.
	 *
	 * Deliberately answers FALSE rather than throwing for every other id: that
	 * is what Nextcloud does, and it is the whole reason a stale literal goes
	 * dark instead of erroring.
	 *
	 * @param list<string> $installed Ids this fake instance registered.
	 * @param list<string> $enabled   Ids also enabled for the current user.
	 */
	private function appManager(array $installed, ?array $enabled = null): IAppManager {
		$enabled = ($enabled ?? $installed);
		$manager = $this->createMock(originalClassName: IAppManager::class);
		$manager->method('isInstalled')
			->willReturnCallback(static fn (string $appId): bool => in_array($appId, $installed, true));
		$manager->method('isEnabledForUser')
			->willReturnCallback(static fn (string $appId): bool => in_array($appId, $enabled, true));
		return $manager;
	}//end appManager()

	// -------------------------------------------------------------
	// resolve() — the id half.
	// -------------------------------------------------------------

	public function testResolvePrefersTheNewIdOnAMigratedInstance(): void {
		$manager = $this->appManager(installed: ['integriq']);
		$this->assertSame('integriq', FleetAppId::resolve(appManager: $manager, canonical: 'integriq'));
	}//end testResolvePrefersTheNewIdOnAMigratedInstance()

	/**
	 * The case a hard swap to the new literal breaks.
	 */
	public function testResolveFallsBackToTheOldIdOnAPreRenameInstance(): void {
		$manager = $this->appManager(installed: ['openconnector']);
		$this->assertSame('openconnector', FleetAppId::resolve(appManager: $manager, canonical: 'integriq'));
	}//end testResolveFallsBackToTheOldIdOnAPreRenameInstance()

	/**
	 * Order is the contract: newest first, even when both answer.
	 */
	public function testResolvePrefersTheNewIdWhenBothAreInstalled(): void {
		$manager = $this->appManager(installed: ['openconnector', 'integriq']);
		$this->assertSame('integriq', FleetAppId::resolve(appManager: $manager, canonical: 'integriq'));
	}//end testResolvePrefersTheNewIdWhenBothAreInstalled()

	public function testResolveReturnsNullWhenNoCandidateIsInstalled(): void {
		$manager = $this->appManager(installed: []);
		$this->assertNull(FleetAppId::resolve(appManager: $manager, canonical: 'integriq'));
	}//end testResolveReturnsNullWhenNoCandidateIsInstalled()

	/**
	 * An app with no rename entry still resolves under its own name, so a
	 * caller need not check the map before asking.
	 */
	public function testResolveFallsBackToTheCanonicalNameForAnUnmappedApp(): void {
		$manager = $this->appManager(installed: ['openregister']);
		$this->assertSame('openregister', FleetAppId::resolve(appManager: $manager, canonical: 'openregister'));
	}//end testResolveFallsBackToTheCanonicalNameForAnUnmappedApp()

	/**
	 * One unanswerable candidate must not abort the search.
	 *
	 * Without the per-candidate catch, an app manager that raises on the FIRST
	 * (new) id would report the app absent on an instance that has it under
	 * the old one — a silent no-op produced by the resolver itself.
	 */
	public function testResolveKeepsSearchingWhenACandidateThrows(): void {
		$manager = $this->createMock(originalClassName: IAppManager::class);
		$manager->method('isInstalled')->willReturnCallback(
			static function (string $appId): bool {
				if ($appId === 'integriq') {
					throw new RuntimeException('app manager blew up');
				}

				return $appId === 'openconnector';
			}
		);

		$this->assertSame('openconnector', FleetAppId::resolve(appManager: $manager, canonical: 'integriq'));
	}//end testResolveKeepsSearchingWhenACandidateThrows()

	public function testIsInstalledReportsPresenceUnderEitherName(): void {
		$this->assertTrue(FleetAppId::isInstalled($this->appManager(installed: ['procest']), 'dossiq'));
		$this->assertTrue(FleetAppId::isInstalled($this->appManager(installed: ['dossiq']), 'dossiq'));
		$this->assertFalse(FleetAppId::isInstalled($this->appManager(installed: []), 'dossiq'));
	}//end testIsInstalledReportsPresenceUnderEitherName()

	// -------------------------------------------------------------
	// isEnabledForUser() — presence and enablement are separate questions.
	// -------------------------------------------------------------

	public function testIsEnabledForUserChecksTheIdTheInstanceActuallyHas(): void {
		$manager = $this->appManager(installed: ['openconnector']);
		$this->assertTrue(FleetAppId::isEnabledForUser(appManager: $manager, canonical: 'integriq'));
	}//end testIsEnabledForUserChecksTheIdTheInstanceActuallyHas()

	/**
	 * Installed but disabled for this user is not available.
	 */
	public function testIsEnabledForUserFalseWhenInstalledButDisabled(): void {
		$manager = $this->appManager(installed: ['integriq'], enabled: []);
		$this->assertFalse(FleetAppId::isEnabledForUser(appManager: $manager, canonical: 'integriq'));
	}//end testIsEnabledForUserFalseWhenInstalledButDisabled()

	public function testIsEnabledForUserFalseWhenAbsentEntirely(): void {
		$manager = $this->appManager(installed: []);
		$this->assertFalse(FleetAppId::isEnabledForUser(appManager: $manager, canonical: 'integriq'));
	}//end testIsEnabledForUserFalseWhenAbsentEntirely()

	/**
	 * A throwing enablement check degrades to "unavailable", never a fatal.
	 */
	public function testIsEnabledForUserFalseWhenTheCheckThrows(): void {
		$manager = $this->createMock(originalClassName: IAppManager::class);
		$manager->method('isInstalled')->willReturn(true);
		$manager->method('isEnabledForUser')->willThrowException(new RuntimeException('boom'));

		$this->assertFalse(FleetAppId::isEnabledForUser(appManager: $manager, canonical: 'integriq'));
	}//end testIsEnabledForUserFalseWhenTheCheckThrows()

	// -------------------------------------------------------------
	// appPath() — a URL is a routing key, valid only for the registered id.
	// -------------------------------------------------------------

	public function testAppPathUsesTheRegisteredIdNotTheCanonicalOne(): void {
		$manager = $this->appManager(installed: ['openconnector']);
		$this->assertSame(
			'/apps/openconnector/api/sources',
			FleetAppId::appPath(appManager: $manager, canonical: 'integriq', suffix: 'api/sources')
		);
	}//end testAppPathUsesTheRegisteredIdNotTheCanonicalOne()

	public function testAppPathWithoutASuffixIsTheBareAppRoot(): void {
		$manager = $this->appManager(installed: ['integriq']);
		$this->assertSame('/apps/integriq', FleetAppId::appPath(appManager: $manager, canonical: 'integriq'));
	}//end testAppPathWithoutASuffixIsTheBareAppRoot()

	/**
	 * A caller that supplies a leading slash must not produce `/apps/x//y`.
	 */
	public function testAppPathDoesNotDoubleTheSeparator(): void {
		$manager = $this->appManager(installed: ['integriq']);
		$this->assertSame(
			'/apps/integriq/api/health',
			FleetAppId::appPath(appManager: $manager, canonical: 'integriq', suffix: '/api/health')
		);
	}//end testAppPathDoesNotDoubleTheSeparator()

	/**
	 * No installed id means no valid path — null, never a URL that 404s.
	 */
	public function testAppPathReturnsNullWhenTheAppIsAbsent(): void {
		$manager = $this->appManager(installed: []);
		$this->assertNull(FleetAppId::appPath(appManager: $manager, canonical: 'integriq', suffix: 'api/sources'));
	}//end testAppPathReturnsNullWhenTheAppIsAbsent()

	// -------------------------------------------------------------
	// classCandidates() — the namespace half.
	// -------------------------------------------------------------

	public function testClassCandidatesAreNewestFirst(): void {
		$this->assertSame(
			[
				'OCA\Integriq\Service\Datasource\DashboardDatasourceService',
				'OCA\OpenConnector\Service\Datasource\DashboardDatasourceService',
			],
			FleetAppId::classCandidates(canonical: 'integriq', relative: 'Service\Datasource\DashboardDatasourceService')
		);
	}//end testClassCandidatesAreNewestFirst()

	/**
	 * `openbuild` shipped `OCA\OpenBuilt`, which no naming rule produces.
	 *
	 * Pinned because it is the entry most likely to be "corrected" to
	 * `OCA\OpenBuild` by someone deriving the namespace from the id, which
	 * would take every buildiq binding dark without an error.
	 */
	public function testClassCandidatesPreserveTheIrregularBuildiqNamespace(): void {
		$this->assertSame(
			['OCA\Buildiq\Service\Thing', 'OCA\OpenBuilt\Service\Thing'],
			FleetAppId::classCandidates(canonical: 'buildiq', relative: 'Service\Thing')
		);
	}//end testClassCandidatesPreserveTheIrregularBuildiqNamespace()

	public function testClassCandidatesToleratesALeadingSeparator(): void {
		$this->assertSame(
			['OCA\Thematiq\Icon', 'OCA\NLDesign\Icon'],
			FleetAppId::classCandidates(canonical: 'thematiq', relative: '\\Icon')
		);
	}//end testClassCandidatesToleratesALeadingSeparator()

	public function testClassCandidatesAreEmptyForAnUnmappedApp(): void {
		$this->assertSame([], FleetAppId::classCandidates(canonical: 'openregister', relative: 'Service\Thing'));
	}//end testClassCandidatesAreEmptyForAnUnmappedApp()

	// -------------------------------------------------------------
	// resolveClass() / isInstanceOf().
	// -------------------------------------------------------------

	/**
	 * Only the OLD namespace exists here (see the fixture at the foot of this
	 * file), which is the pre-rename instance the fallback exists for.
	 */
	public function testResolveClassFindsTheOldNamespaceWhenTheNewOneIsAbsent(): void {
		$this->assertSame(
			'OCA\OpenBuilt\Fixture\LegacyProbe',
			FleetAppId::resolveClass(canonical: 'buildiq', relative: 'Fixture\LegacyProbe')
		);
	}//end testResolveClassFindsTheOldNamespaceWhenTheNewOneIsAbsent()

	public function testResolveClassReturnsNullWhenNoCandidateExists(): void {
		$this->assertNull(FleetAppId::resolveClass(canonical: 'buildiq', relative: 'Fixture\NoSuchProbe'));
	}//end testResolveClassReturnsNullWhenNoCandidateExists()

	public function testIsInstanceOfMatchesUnderTheOldNamespace(): void {
		$event = new \OCA\OpenBuilt\Fixture\LegacyProbe();
		$this->assertTrue(FleetAppId::isInstanceOf($event, 'buildiq', 'Fixture\LegacyProbe'));
	}//end testIsInstanceOfMatchesUnderTheOldNamespace()

	public function testIsInstanceOfFalseForAnUnrelatedValue(): void {
		$this->assertFalse(FleetAppId::isInstanceOf(new \stdClass(), 'buildiq', 'Fixture\LegacyProbe'));
	}//end testIsInstanceOfFalseForAnUnrelatedValue()

	// -------------------------------------------------------------
	// getService() / hasService() — the container half.
	// -------------------------------------------------------------

	/**
	 * A container that only knows the OLD name still yields the service.
	 *
	 * This is the exact shape that took thirteen filinq bindings dark: the
	 * caller asks for one FQCN, `get()` throws, and the surrounding try/catch
	 * turns it into a feature that quietly stops working.
	 */
	public function testGetServiceFallsBackToTheOldNamespace(): void {
		$service = new \stdClass();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use ($service): object {
				if ($id === 'OCA\OpenConnector\Service\Datasource\DashboardDatasourceService') {
					return $service;
				}

				throw new class ('not registered') extends RuntimeException implements \Psr\Container\NotFoundExceptionInterface {
				};
			}
		);

		$this->assertSame(
			$service,
			FleetAppId::getService($container, 'integriq', 'Service\Datasource\DashboardDatasourceService')
		);
	}//end testGetServiceFallsBackToTheOldNamespace()

	public function testGetServiceReturnsNullWhenNoCandidateResolves(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('absent'));

		$this->assertNull(
			FleetAppId::getService($container, 'integriq', 'Service\Datasource\DashboardDatasourceService')
		);
	}//end testGetServiceReturnsNullWhenNoCandidateResolves()

	public function testHasServiceTrueWhenOnlyTheOldNameIsRegistered(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('has')->willReturnCallback(
			static fn (string $id): bool => $id === 'OCA\OpenConnector\Service\Datasource\DashboardDatasourceService'
		);

		$this->assertTrue(
			FleetAppId::hasService($container, 'integriq', 'Service\Datasource\DashboardDatasourceService')
		);
	}//end testHasServiceTrueWhenOnlyTheOldNameIsRegistered()

	public function testHasServiceFalseWhenNoCandidateIsRegistered(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('has')->willReturn(false);

		$this->assertFalse(
			FleetAppId::hasService($container, 'integriq', 'Service\Datasource\DashboardDatasourceService')
		);
	}//end testHasServiceFalseWhenNoCandidateIsRegistered()

	/**
	 * A container that raises on `has()` must read as absent, not crash.
	 */
	public function testHasServiceFalseWhenTheContainerThrows(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('has')->willThrowException(new RuntimeException('container blew up'));

		$this->assertFalse(
			FleetAppId::hasService($container, 'integriq', 'Service\Datasource\DashboardDatasourceService')
		);
	}//end testHasServiceFalseWhenTheContainerThrows()

}//end class
