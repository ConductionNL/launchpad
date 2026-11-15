<?php

/**
 * FleetAppIdPreferenceTest
 *
 * Pins what FleetAppIdTest leaves open: the newest name wins when both the
 * renamed and the pre-rename service answer, a candidate that answers with a
 * non-object is passed over, a throwing `has()` does not stop the search,
 * and every mapped app id and namespace pair is listed newest first.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Support\FleetAppId;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Candidate order and skipping in FleetAppId.
 *
 * @covers \OCA\LaunchPad\Support\FleetAppId
 */
class FleetAppIdPreferenceTest extends TestCase {

	private const RELATIVE = 'Service\Datasource\DashboardDatasourceService';

	/**
	 * With both names registered, the renamed app's service is returned and
	 * the old name is never asked.
	 *
	 * @return void
	 */
	public function testGetServicePrefersTheNewNamespace(): void {
		$new = new \stdClass();
		$asked = [];
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use ($new, &$asked): object {
				$asked[] = $id;
				return $id === 'OCA\Integriq\\' . self::RELATIVE ? $new : new \stdClass();
			}
		);

		$this->assertSame($new, FleetAppId::getService($container, 'integriq', self::RELATIVE));
		$this->assertSame(['OCA\Integriq\\' . self::RELATIVE], $asked);
	}//end testGetServicePrefersTheNewNamespace()

	/**
	 * A candidate that answers with something other than an object is passed over.
	 *
	 * @return void
	 */
	public function testGetServiceSkipsANonObjectAnswer(): void {
		$old = new \stdClass();
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): mixed => $id === 'OCA\Integriq\\' . self::RELATIVE ? 'not a service' : $old
		);

		$this->assertSame($old, FleetAppId::getService($container, 'integriq', self::RELATIVE));
	}//end testGetServiceSkipsANonObjectAnswer()

	/**
	 * A container whose has() throws for the new name still finds the old one.
	 *
	 * @return void
	 */
	public function testHasServiceKeepsSearchingAfterAThrow(): void {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('has')->willReturnCallback(
			static function (string $id): bool {
				if ($id === 'OCA\Integriq\\' . self::RELATIVE) {
					throw new RuntimeException('container broken');
				}

				return $id === 'OCA\OpenConnector\\' . self::RELATIVE;
			}
		);

		$this->assertTrue(FleetAppId::hasService($container, 'integriq', self::RELATIVE));
	}//end testHasServiceKeepsSearchingAfterAThrow()

	/**
	 * Renamed app, its pre-rename id, and the namespace of each.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
	 */
	public static function renames(): array {
		return [
			'integriq' => ['integriq', 'openconnector', 'OCA\Integriq', 'OCA\OpenConnector'],
			'filinq'   => ['filinq', 'docudesk', 'OCA\Filinq', 'OCA\DocuDesk'],
			'thematiq' => ['thematiq', 'nldesign', 'OCA\Thematiq', 'OCA\NLDesign'],
			'stackiq'  => ['stackiq', 'softwarecatalog', 'OCA\Stackiq', 'OCA\SoftwareCatalog'],
			'larpinq'  => ['larpinq', 'larpingapp', 'OCA\Larpinq', 'OCA\LarpingApp'],
			'dossiq'   => ['dossiq', 'procest', 'OCA\Dossiq', 'OCA\Procest'],
			'learniq'  => ['learniq', 'scholiq', 'OCA\Learniq', 'OCA\Scholiq'],
			'decidiq'  => ['decidiq', 'decidesk', 'OCA\Decidiq', 'OCA\Decidesk'],
			'buildiq'  => ['buildiq', 'openbuild', 'OCA\Buildiq', 'OCA\OpenBuilt'],
			'keepiq'   => ['keepiq', 'doriath', 'OCA\Keepiq', 'OCA\Doriath'],
		];
	}//end renames()

	/**
	 * Every renamed app resolves to its old id only when the new one is absent,
	 * and its class candidates are the new namespace, then the old.
	 *
	 * @param string $canonical    The new app id.
	 * @param string $oldId        The pre-rename app id.
	 * @param string $newNamespace The new namespace.
	 * @param string $oldNamespace The pre-rename namespace.
	 *
	 * @return void
	 */
	#[DataProvider('renames')]
	public function testEveryRenameIsMappedNewestFirst(
		string $canonical,
		string $oldId,
		string $newNamespace,
		string $oldNamespace,
	): void {
		$installed = function (array $ids): IAppManager {
			$manager = $this->createMock(IAppManager::class);
			$manager->method('isInstalled')->willReturnCallback(static fn (string $id): bool => in_array($id, $ids, true));
			return $manager;
		};

		$this->assertSame(
			[$canonical, $canonical, $oldId],
			[
				FleetAppId::resolve($installed([$canonical, $oldId]), $canonical),
				FleetAppId::resolve($installed([$canonical]), $canonical),
				FleetAppId::resolve($installed([$oldId]), $canonical),
			]
		);
		$this->assertSame(
			[$newNamespace . '\Db\Thing', $oldNamespace . '\Db\Thing'],
			FleetAppId::classCandidates($canonical, 'Db\Thing')
		);
	}//end testEveryRenameIsMappedNewestFirst()
}//end class
