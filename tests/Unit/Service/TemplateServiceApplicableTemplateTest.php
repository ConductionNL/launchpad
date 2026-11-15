<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Which template a user gets when several target one of their groups:
 * REQ-TMPL-021. The mapper hands the templates over sorted by name, as
 * `findAdminTemplates()` does, so a rule that still took "the first one"
 * would pick by name and fail here.
 */
class TemplateServiceApplicableTemplateTest extends TestCase {
	/**
	 * @param array<int, string> $groups Target groups.
	 */
	private static function template(int $id, string $name, array $groups, string $uuid = ''): Dashboard {
		$template = new Dashboard();
		$template->setId($id);
		$template->setUuid($uuid === '' ? 'uuid-' . $id : $uuid);
		$template->setName($name);
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$template->setTargetGroupsArray($groups);

		return $template;
	}

	/**
	 * @param array<int, Dashboard> $templates Every admin template.
	 * @param array<string, string|int> $config App config.
	 */
	private function serviceFor(array $templates, array $config = [], ?Dashboard $default = null): TemplateService {
		usort($templates, static fn (Dashboard $a, Dashboard $b): int => strcmp($a->getName(), $b->getName()));
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findAdminTemplates')->willReturn($templates);
		if ($default === null) {
			$mapper->method('findDefaultTemplate')->willThrowException(new DoesNotExistException('none'));
		} else {
			$mapper->method('findDefaultTemplate')->willReturn($default);
		}

		$admin = $this->createMock(AdminTemplateService::class);
		$admin->method('getUserGroupIdsFor')->willReturn(['behandelaars', 'iedereen']);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => (string)($config[$key] ?? $default)
		);
		$appConfig->method('getValueInt')->willReturnCallback(
			static fn (string $app, string $key, int $default = 0): int => (int)($config[$key] ?? $default)
		);

		return new TemplateService(
			dashboardMapper: $mapper,
			placementMapper: $this->createMock(WidgetPlacementMapper::class),
			adminTemplateService: $admin,
			appConfig: $appConfig,
		);
	}

	public function testTheLowestIdWinsAmongHandMadeTemplates(): void {
		$service = $this->serviceFor([
			self::template(9, 'Aanvragen', ['behandelaars']),
			self::template(4, 'Zaken', ['iedereen']),
			self::template(2, 'Bestuur', ['bestuur']),
		]);

		self::assertSame(4, $service->getApplicableTemplate(userId: 'pieter')->getId());
	}

	public function testTheInstalledShippedTemplateGoesBeforeAnEarlierForcedCopy(): void {
		// The case of issue #781: `--force` left the old copy (id 7) next to
		// the new one (id 9), both for the same group. The recorded install
		// is the new one.
		$service = $this->serviceFor(
			[
				self::template(7, 'Mijn werkdag', ['behandelaars'], 'old-uuid'),
				self::template(9, 'Mijn werkdag', ['behandelaars'], 'new-uuid'),
			],
			['shipped_template_mijn-werkdag' => 'new-uuid', 'shipped_template_version_mijn-werkdag' => 2]
		);

		self::assertSame(9, $service->getApplicableTemplate(userId: 'pieter')->getId());
	}

	public function testAShippedTemplateGoesBeforeAHandMadeOneWithALowerId(): void {
		$service = $this->serviceFor(
			[
				self::template(3, 'Afdeling', ['behandelaars']),
				self::template(9, 'Mijn werkdag', ['iedereen'], 'new-uuid'),
			],
			['shipped_template_mijn-werkdag' => 'new-uuid']
		);

		self::assertSame(9, $service->getApplicableTemplate(userId: 'pieter')->getId());
	}

	public function testAGroupTemplateStillGoesBeforeTheDefault(): void {
		$default = self::template(1, 'Iedereen', []);
		$service = $this->serviceFor([$default, self::template(5, 'Zaken', ['behandelaars'])], [], $default);

		self::assertSame(5, $service->getApplicableTemplate(userId: 'pieter')->getId());
	}

	public function testWithoutAGroupTemplateTheDefaultApplies(): void {
		$default = self::template(1, 'Iedereen', []);
		$service = $this->serviceFor([$default, self::template(5, 'Bestuur', ['bestuur'])], [], $default);

		self::assertSame(1, $service->getApplicableTemplate(userId: 'pieter')->getId());
	}

	public function testNoTemplateAtAll(): void {
		self::assertNull($this->serviceFor([])->getApplicableTemplate(userId: 'pieter'));
	}
}
