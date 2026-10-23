<?php

/**
 * OfficeNetworkServiceTest
 *
 * Office networks (launcher-tile-internal-address, REQ-TIA-001): ranges are
 * validated and stored, and the request address decides, for IPv4 and IPv6;
 * an empty list means nobody is on the office network.
 *
 * @category  Test
 * @package   Unit\Service
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
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\OfficeNetworkService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Unit\Support\CidrIpFactory;

class OfficeNetworkServiceTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	private string $remote = '203.0.113.9';

	private function service(): OfficeNetworkService {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$mapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value): AdminSetting {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);
		$request = $this->createMock(IRequest::class);
		$request->method('getRemoteAddress')->willReturnCallback(fn (): string => $this->remote);

		return new OfficeNetworkService(settingMapper: $mapper, request: $request, ipFactory: new CidrIpFactory());
	}//end service()

	public function testAnEmptyListMeansNobodyIsOnTheOfficeNetwork(): void {
		$this->remote = '10.1.2.3';
		$this->assertFalse($this->service()->isOfficeRequest());
	}//end testAnEmptyListMeansNobodyIsOnTheOfficeNetwork()

	public function testAnIpv4RequestInsideARangeIsOnTheOfficeNetwork(): void {
		$service = $this->service();
		$this->assertSame(['10.0.0.0/8', '192.168.12.0/24'], $service->saveRanges(raw: [' 10.0.0.0/8 ', '', '192.168.12.0/24', '10.0.0.0/8']));

		$this->remote = '10.20.30.40';
		$this->assertTrue($service->isOfficeRequest());
		$this->remote = '192.168.13.1';
		$this->assertFalse($service->isOfficeRequest(), 'one subnet over is outside');
		$this->remote = '203.0.113.9';
		$this->assertFalse($service->isOfficeRequest());
	}//end testAnIpv4RequestInsideARangeIsOnTheOfficeNetwork()

	public function testAnIpv6RangeMatchesOnlyIpv6(): void {
		$service = $this->service();
		$service->saveRanges(raw: ['2001:db8:12::/48']);

		$this->remote = '2001:db8:12:ff::1';
		$this->assertTrue($service->isOfficeRequest());
		$this->remote = '2001:db8:13::1';
		$this->assertFalse($service->isOfficeRequest());
		$this->remote = '10.0.0.1';
		$this->assertFalse($service->isOfficeRequest());
	}//end testAnIpv6RangeMatchesOnlyIpv6()

	public function testAMalformedRangeIsRefusedAndNothingStored(): void {
		try {
			$this->service()->saveRanges(raw: ['10.0.0.0/8', '10.0.0.0/33']);
			$this->fail('a /33 was accepted');
		} catch (InvalidArgumentException) {
			$this->assertSame([], $this->settings);
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service()->saveRanges(raw: ['kantoor']);
	}//end testAMalformedRangeIsRefusedAndNothingStored()

	public function testAnUnparsableRemoteAddressIsNotTheOfficeNetwork(): void {
		$service = $this->service();
		$service->saveRanges(raw: ['0.0.0.0/0']);
		$this->remote = 'not-an-ip';

		$this->assertFalse($service->isOfficeRequest());
	}//end testAnUnparsableRemoteAddressIsNotTheOfficeNetwork()
}//end class
