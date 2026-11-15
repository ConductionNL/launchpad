<?php

/**
 * DashboardTranslationServiceGoldenTest
 *
 * Records the mapper and transaction calls, the written fields and the
 * outcome of the translation service's write and resolve paths, so the
 * split of those methods keeps them identical.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class DashboardTranslationServiceGoldenTest extends TestCase {

	/** @var list<string> */
	private array $log = [];

	/** @var array<string, DashboardTranslation> Variants by language. */
	private array $variants = [];

	private bool $failUpdate = false;

	private DashboardTranslationService $service;

	protected function setUp(): void {
		$mapper = $this->createMock(DashboardTranslationMapper::class);
		$mapper->method('findByDashboardUuidAndLanguage')->willReturnCallback(
			function (string $dashboardUuid, string $languageCode): ?DashboardTranslation {
				$this->log[] = 'find ' . $dashboardUuid . ' ' . $languageCode;
				return $this->variants[$languageCode] ?? null;
			}
		);
		$mapper->method('findPrimaryByDashboardUuid')->willReturnCallback(
			function (string $dashboardUuid): ?DashboardTranslation {
				$this->log[] = 'findPrimary ' . $dashboardUuid;
				foreach ($this->variants as $variant) {
					if ((int)$variant->getIsPrimary() === 1) {
						return $variant;
					}
				}

				return null;
			}
		);
		foreach (['insert', 'update'] as $write) {
			$mapper->method($write)->willReturnCallback(function (DashboardTranslation $t) use ($write): DashboardTranslation {
				if ($write === 'update' && $this->failUpdate === true) {
					$this->log[] = 'update failed';
					throw new \RuntimeException('update failed');
				}

				$this->log[] = $write . ' ' . self::fields($t);
				return $t;
			});
		}

		$mapper->method('clearPrimary')->willReturnCallback(function (string $dashboardUuid, ?int $exceptId): int {
			$this->log[] = 'clearPrimary ' . $dashboardUuid . ' ' . (string)$exceptId;
			return 1;
		});

		$db = $this->createMock(IDBConnection::class);
		foreach (['beginTransaction', 'commit', 'rollBack'] as $call) {
			$db->method($call)->willReturnCallback(function () use ($call): void {
				$this->log[] = $call;
			});
		}

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $userId, string $appName, string $key, string $default): string => ($userId === 'alice' ? 'nl_NL' : '')
		);

		$this->service = new DashboardTranslationService(translationMapper: $mapper, db: $db, config: $config);

		$this->variants = [
			'en' => self::variant(7, 'en', 1, 'Home', 'English', '{"en":1}'),
			'de' => self::variant(8, 'de', 0, 'Heim', 'Deutsch', '{"de":1}'),
		];
	}

	private static function variant(int $id, string $lang, int $primary, ?string $name, ?string $description, ?string $tree): DashboardTranslation {
		$t = new DashboardTranslation();
		$t->setId($id);
		$t->setDashboardUuid('d-1');
		$t->setLanguageCode($lang);
		$t->setIsPrimary($primary);
		$t->setName($name);
		$t->setDescription($description);
		$t->setWidgetTreeJson($tree);
		return $t;
	}

	private static function fields(DashboardTranslation $t): string {
		return json_encode([
			$t->getDashboardUuid(), $t->getLanguageCode(), $t->getName(), $t->getDescription(),
			$t->getWidgetTreeJson(), $t->getIsPrimary(), $t->getCreatedAt() !== null, $t->getUpdatedAt() !== null,
		]);
	}

	/**
	 * @return array{0: mixed, 1: list<string>}
	 */
	private function record(callable $call): array {
		$this->log = [];
		try {
			$outcome = $call();
			if ($outcome instanceof DashboardTranslation) {
				$outcome = self::fields($outcome);
			} elseif (is_array($outcome) === true) {
				$outcome = [self::fields($outcome['translation']), $outcome['isFallback']];
			}
		} catch (\Throwable $e) {
			$outcome = $e::class . ': ' . $e->getMessage();
		}

		return [$outcome, $this->log];
	}

	public function testCreateVariant(): void {
		$this->assertSame(
			['InvalidArgumentException: ' . DashboardTranslationService::ERR_INVALID_LANGUAGE, []],
			$this->record(fn () => $this->service->createVariant('d-1', ''))
		);
		$this->assertSame(
			['Exception: ' . DashboardTranslationService::ERR_LANGUAGE_EXISTS, ['find d-1 de']],
			$this->record(fn () => $this->service->createVariant('d-1', 'DE'))
		);
		$this->assertSame(
			['["d-1","fr","Accueil","Deutsch","{\"de\":1}",0,true,true]', [
				'find d-1 fr', 'find d-1 de', 'insert ["d-1","fr","Accueil","Deutsch","{\"de\":1}",0,true,true]',
			]],
			$this->record(fn () => $this->service->createVariant('d-1', 'fr', ['name' => 'Accueil', 'copyFromLanguage' => 'de']))
		);
		$this->assertSame(
			['["d-1","fr","Home","English","{}",0,true,true]', [
				'find d-1 fr', 'findPrimary d-1', 'insert ["d-1","fr","Home","English","{}",0,true,true]',
			]],
			$this->record(fn () => $this->service->createVariant('d-1', 'fr', ['widgetTreeJson' => '{}', 'copyFromLanguage' => 'PRIMARY']))
		);
		$this->assertSame(
			['["d-1","fr",null,null,null,0,true,true]', ['find d-1 fr', 'find d-1 it', 'insert ["d-1","fr",null,null,null,0,true,true]']],
			$this->record(fn () => $this->service->createVariant('d-1', 'fr', ['copyFromLanguage' => 'it']))
		);
	}

	public function testSeedAndMaterialise(): void {
		$dashboard = new Dashboard();
		$dashboard->setUuid('d-2');
		$dashboard->setUserId('alice');
		$dashboard->setName('Mine');
		$dashboard->setDescription('Desc');

		$this->variants = [];
		$this->assertSame(
			['["d-2","nl","Mine","Desc",null,1,true,true]', ['findPrimary d-2', 'insert ["d-2","nl","Mine","Desc",null,1,true,true]']],
			$this->record(fn () => $this->service->seedPrimaryFor($dashboard))
		);
		$this->assertSame(
			['["d-2","nl","Mine","Desc",null,1,false,false]', []],
			$this->record(fn () => $this->service->materialiseLegacyVariant($dashboard))
		);

		$dashboard->setUserId(null);
		$this->assertSame(
			['["d-2","en","Mine","Desc",null,1,false,false]', []],
			$this->record(fn () => $this->service->materialiseLegacyVariant($dashboard))
		);

		$this->assertSame(
			['InvalidArgumentException: Cannot seed translation: dashboard has no UUID', []],
			$this->record(fn () => $this->service->seedPrimaryFor(new Dashboard()))
		);
	}

	public function testUpdatePromoteAndResolve(): void {
		$this->assertSame(
			['["d-1","de",null,"Deutsch","{\"de\":1}",0,false,true]', ['find d-1 de', 'update ["d-1","de",null,"Deutsch","{\"de\":1}",0,false,true]']],
			$this->record(fn () => $this->service->updateVariant('d-1', 'de', ['name' => null]))
		);
		$this->assertSame(
			['["d-1","de","Heim","Deutsch","{\"de\":1}",1,false,true]', [
				'find d-1 de', 'beginTransaction', 'clearPrimary d-1 8', 'update ["d-1","de","Heim","Deutsch","{\"de\":1}",1,false,true]', 'commit',
			]],
			$this->record(function () {
				$this->variants['de']->setName('Heim');
				return $this->service->promoteVariantToPrimary('d-1', 'de');
			})
		);
		$this->assertSame(['["d-1","en","Home","English","{\"en\":1}",1,false,false]', ['find d-1 en']], $this->record(fn () => $this->service->promoteVariantToPrimary('d-1', 'en')));

		$this->variants['de']->setIsPrimary(0);
		$this->failUpdate = true;
		$this->assertSame(
			['RuntimeException: update failed', ['find d-1 de', 'beginTransaction', 'clearPrimary d-1 8', 'update failed', 'rollBack']],
			$this->record(fn () => $this->service->promoteVariantToPrimary('d-1', 'de'))
		);

		$this->assertSame([['["d-1","de","Heim","Deutsch","{\"de\":1}",1,false,true]', false], ['find d-1 de']], $this->record(fn () => $this->service->resolveForLocale('d-1', 'de_DE')));
		$this->assertSame([['["d-1","en","Home","English","{\"en\":1}",1,false,false]', true], ['find d-1 fr', 'findPrimary d-1']], $this->record(fn () => $this->service->resolveForLocale('d-1', 'fr')));
		$this->assertSame([['["d-1","en","Home","English","{\"en\":1}",1,false,false]', true], ['findPrimary d-1']], $this->record(fn () => $this->service->resolveForLocale('d-1', '')));
		$this->variants = [];
		$this->assertSame([null, ['find d-1 fr', 'findPrimary d-1']], $this->record(fn () => $this->service->resolveForLocale('d-1', 'fr')));
	}
}
