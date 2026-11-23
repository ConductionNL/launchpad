<?php

/**
 * ImageHostServiceTest
 *
 * REQ-IMG-006: the external image host allow-list reads fail-closed and only
 * stores bare host names.
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

use InvalidArgumentException;
use OCA\LaunchPad\Service\ImageHostService;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ImageHostServiceTest extends TestCase {

	/**
	 * A service over an app config holding the given raw value.
	 *
	 * @param string $raw The stored `image_allowed_hosts` value.
	 *
	 * @return ImageHostService
	 */
	private function serviceWith(string $raw): ImageHostService {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $key === 'image_allowed_hosts' ? $raw : $default
		);

		return new ImageHostService(appConfig: $config);
	}//end serviceWith()

	/**
	 * @return array<string, array{string}>
	 */
	public static function failClosedValues(): array {
		return [
			'unset'     => [''],
			'empty'     => ['[]'],
			'malformed' => ['{not json'],
			'object'    => ['"example.com"'],
		];
	}//end failClosedValues()

	#[DataProvider('failClosedValues')]
	public function testAnUnusableListAdmitsNoHost(string $raw): void {
		$this->assertSame([], $this->serviceWith(raw: $raw)->getAllowedHosts());
	}//end testAnUnusableListAdmitsNoHost()

	public function testReadsTheStoredHosts(): void {
		$hosts = $this->serviceWith(raw: '["example.com", " cdn.example.org ", "", 7, "example.com"]')->getAllowedHosts();

		$this->assertSame(['example.com', 'cdn.example.org'], $hosts);
	}//end testReadsTheStoredHosts()

	public function testStoresNormalisedHostNames(): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->expects($this->once())->method('setValueString')
			->with('launchpad', 'image_allowed_hosts', '["example.com","cdn.example.org:8443"]');
		$service = new ImageHostService(appConfig: $config);

		$stored = $service->setAllowedHosts(hosts: [' Example.COM ', 'cdn.example.org:8443', '', 'example.com']);

		$this->assertSame(['example.com', 'cdn.example.org:8443'], $stored);
	}//end testStoresNormalisedHostNames()

	public function testAnEmptyListIsStored(): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->expects($this->once())->method('setValueString')->with('launchpad', 'image_allowed_hosts', '[]');

		$this->assertSame([], (new ImageHostService(appConfig: $config))->setAllowedHosts(hosts: []));
	}//end testAnEmptyListIsStored()

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function refusedLists(): array {
		return [
			'not a list'  => ['example.com'],
			'not strings' => [[42]],
			'scheme'      => [['https://example.com']],
			'path'        => [['example.com/images']],
			'wildcard'    => [['*.example.com']],
			'bare star'   => [['*']],
			'space'       => [['exa mple.com']],
		];
	}//end refusedLists()

	#[DataProvider('refusedLists')]
	public function testRefusesAnythingButHostNames(mixed $hosts): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->expects($this->never())->method('setValueString');

		$this->expectException(InvalidArgumentException::class);
		(new ImageHostService(appConfig: $config))->setAllowedHosts(hosts: $hosts);
	}//end testRefusesAnythingButHostNames()
}//end class
