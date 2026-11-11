<?php

/**
 * ActionAuthMatrixGoldenTest
 *
 * Pins how the action matrix is normalised when it is read from and
 * written to the app config: invalid JSON, a non-map, non-string actions,
 * non-list groups, empty and non-string group entries and duplicates.
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

use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCP\IAppConfig;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

class ActionAuthMatrixGoldenTest extends TestCase {

	private string $stored = '{}';

	private ActionAuthService $service;

	protected function setUp(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(fn (): string => $this->stored);
		$config->method('setValueString')->willReturnCallback(function (string $app, string $key, string $value): bool {
			$this->stored = $value;
			return true;
		});

		$this->service = new ActionAuthService(
			appConfig: $config,
			groupManager: $this->createMock(IGroupManager::class),
			adminTemplateService: $this->createMock(AdminTemplateService::class)
		);
	}

	public function testReadingNormalises(): void {
		$this->stored = 'not json';
		$this->assertSame([], $this->service->getMatrix());

		$this->stored = '"a string"';
		$this->assertSame([], $this->service->getMatrix());

		$this->stored = '{"dashboard.create":["staff","",3,"staff","admin"],"x":"admin","0":["a"],"tile.edit":[]}';
		$this->assertSame(
			['dashboard.create' => ['staff', 'admin'], 'tile.edit' => []],
			$this->service->getMatrix()
		);
		$this->assertSame(['staff', 'admin'], $this->service->getAllowedGroups('dashboard.create'));
		$this->assertSame(['admin'], $this->service->getAllowedGroups('unknown'));
	}

	public function testWritingNormalises(): void {
		$this->service->setMatrix([
			'dashboard.create' => ['staff', '', null, 'staff', 'admin'],
			'x' => 'admin',
			7 => ['a'],
			'tile.edit' => [],
		]);
		$this->assertSame('{"dashboard.create":["staff","admin"],"tile.edit":[]}', $this->stored);
	}
}
