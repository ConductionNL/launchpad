<?php

/**
 * OfficeNetworkService Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\OfficeNetworkService;
use OCP\IRequest;
use OCP\Security\Ip\IAddress;
use OCP\Security\Ip\IFactory;
use OCP\Security\Ip\IRange;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The request address decides; IPv4 and IPv6 ranges both count; no ranges
 * means never; a malformed range is refused when entered and skipped when
 * stored.
 *
 * @covers \OCA\LaunchPad\Service\OfficeNetworkService
 * @covers \OCA\LaunchPad\Service\AdminSettingsService
 *
 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
 */
class OfficeNetworkServiceTest extends TestCase {

	/**
	 * Stored admin settings.
	 *
	 * @var array<string,mixed>
	 */
	private array $stored = [];

	/**
	 * A CIDR-aware IFactory: the boundary Nextcloud provides, with real matching.
	 *
	 * @return IFactory
	 */
	private function ipFactory(): IFactory {
		return new class implements IFactory {
			public function rangeFromString(string $range): IRange {
				if (preg_match('#^([^/]+)/(\d{1,3})$#', $range, $m) !== 1 || ($packed = @inet_pton($m[1])) === false) {
					throw new InvalidArgumentException('Invalid range');
				}

				$bits = (int)$m[2];
				if ($bits > strlen($packed) * 8) {
					throw new InvalidArgumentException('Invalid range');
				}

				return new class ($packed, $bits, $range) implements IRange {
					public function __construct(private string $packed, private int $bits, private string $text) {
					}

					public static function isValid(string $range): bool {
						return true;
					}

					public function contains(IAddress $address): bool {
						$other = inet_pton((string)$address);
						if (strlen($other) !== strlen($this->packed)) {
							return false;
						}

						$mask = str_repeat("\xff", intdiv($this->bits, 8));
						if ($this->bits % 8 !== 0) {
							$mask .= chr((0xff << (8 - $this->bits % 8)) & 0xff);
						}

						$mask = str_pad($mask, strlen($this->packed), "\x00");
						return ($other & $mask) === ($this->packed & $mask);
					}

					public function __toString(): string {
						return $this->text;
					}
				};
			}

			public function addressFromString(string $ip): IAddress {
				if (@inet_pton($ip) === false) {
					throw new InvalidArgumentException('Invalid IP');
				}

				return new class ($ip) implements IAddress {
					public function __construct(private string $ip) {
					}

					public static function isValid(string $ip): bool {
						return true;
					}

					public function matches(IRange ...$ranges): bool {
						foreach ($ranges as $range) {
							if ($range->contains($this) === true) {
								return true;
							}
						}

						return false;
					}

					public function __toString(): string {
						return $this->ip;
					}
				};
			}
		};
	}//end ipFactory()

	/**
	 * The service for a request from an address, over the stored settings.
	 *
	 * @param string               $address The request address.
	 * @param LoggerInterface|null $logger  The logger.
	 *
	 * @return OfficeNetworkService
	 */
	private function service(string $address, ?LoggerInterface $logger = null): OfficeNetworkService {
		$request = $this->createMock(IRequest::class);
		$request->method('getRemoteAddress')->willReturn($address);
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getAllAsArray')->willReturnCallback(fn (): array => $this->stored);

		return new OfficeNetworkService(
			request: $request,
			ipFactory: $this->ipFactory(),
			settingMapper: $mapper,
			logger: ($logger ?? $this->createMock(LoggerInterface::class))
		);
	}//end service()

	/**
	 * An IPv4 request inside the office range is on the office network; one outside is not.
	 *
	 * @return void
	 */
	public function testAnIpv4AddressInsideARangeIsOnTheOfficeNetwork(): void {
		$this->stored = [AdminSetting::KEY_OFFICE_NETWORKS => ['10.20.0.0/16']];

		$this->assertTrue($this->service(address: '10.20.4.7')->isOfficeRequest());
		$this->assertFalse($this->service(address: '84.1.2.3')->isOfficeRequest());
	}//end testAnIpv4AddressInsideARangeIsOnTheOfficeNetwork()

	/**
	 * IPv6 ranges count too, and an IPv4 address never matches an IPv6 range.
	 *
	 * @return void
	 */
	public function testIpv6RangesCount(): void {
		$this->stored = [AdminSetting::KEY_OFFICE_NETWORKS => ['2001:db8:10::/48']];

		$this->assertTrue($this->service(address: '2001:db8:10:4::7')->isOfficeRequest());
		$this->assertFalse($this->service(address: '2001:db8:11::1')->isOfficeRequest());
		$this->assertFalse($this->service(address: '10.20.4.7')->isOfficeRequest());
	}//end testIpv6RangesCount()

	/**
	 * Without ranges nobody is on the office network.
	 *
	 * @return void
	 */
	public function testNoRangesMeansNeverOnTheOfficeNetwork(): void {
		$this->stored = [];
		$this->assertFalse($this->service(address: '10.20.4.7')->isOfficeRequest());

		$this->stored = [AdminSetting::KEY_OFFICE_NETWORKS => 'not-a-list'];
		$this->assertFalse($this->service(address: '10.20.4.7')->isOfficeRequest());
	}//end testNoRangesMeansNeverOnTheOfficeNetwork()

	/**
	 * A stored range that no longer parses is skipped and logged; the others still count.
	 *
	 * @return void
	 */
	public function testAMalformedStoredRangeIsSkippedAndLogged(): void {
		$this->stored = [AdminSetting::KEY_OFFICE_NETWORKS => ['10.20.0.0/40', '10.20.0.0/16']];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with($this->anything(), ['range' => '10.20.0.0/40']);

		$this->assertTrue($this->service(address: '10.20.4.7', logger: $logger)->isOfficeRequest());
	}//end testAMalformedStoredRangeIsSkippedAndLogged()

	/**
	 * Entered ranges are trimmed and deduplicated; a malformed one is refused with the spec's message.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-administrators-declare-the-office-networks-req-tia-001
	 */
	public function testEnteredRangesAreCheckedAndNormalised(): void {
		$service = $this->service(address: '10.20.4.7');

		$this->assertSame(['10.20.0.0/16', '2001:db8::/32'], $service->normaliseRanges(ranges: [' 10.20.0.0/16 ', '', '2001:db8::/32', '10.20.0.0/16']));

		foreach (['10.20.0.0/40', '10.20.4.7', 'intranet', 42] as $bad) {
			try {
				$service->normaliseRanges(ranges: ['10.20.0.0/16', $bad]);
				$this->fail('The range ' . var_export($bad, true) . ' was accepted.');
			} catch (InvalidArgumentException $e) {
				$this->assertStringStartsWith('This is not a valid network range', $e->getMessage());
			}
		}
	}//end testEnteredRangesAreCheckedAndNormalised()

	/**
	 * The admin settings store the checked list, refuse a malformed one
	 * without storing anything, and show the caller's address and whether it is inside.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-administrators-declare-the-office-networks-req-tia-001
	 */
	public function testTheAdminSettingsStoreAndShowTheOfficeNetworks(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getAllAsArray')->willReturnCallback(fn (): array => $this->stored);
		$written = [];
		$mapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value) use (&$written): AdminSetting {
				$written[$key] = $value;
				$this->stored[$key] = $value;
				return new AdminSetting();
			}
		);
		$settings = new AdminSettingsService(settingMapper: $mapper, officeNetworks: $this->service(address: '10.20.4.7'));

		$settings->updateSettings(settings: ['officeNetworks' => ['10.20.0.0/16 ']]);
		$this->assertSame([AdminSetting::KEY_OFFICE_NETWORKS => ['10.20.0.0/16']], $written);

		$read = $settings->getSettings();
		$this->assertSame(['10.20.0.0/16'], $read['officeNetworks']);
		$this->assertSame('10.20.4.7', $read['currentAddress']);
		$this->assertTrue($read['currentAddressInOfficeNetworks']);

		$written = [];
		try {
			$settings->updateSettings(settings: ['officeNetworks' => ['10.20.0.0/40']]);
			$this->fail('A malformed range was stored.');
		} catch (InvalidArgumentException $e) {
			$this->assertSame([], $written);
			$this->assertSame(['10.20.0.0/16'], $settings->getSettings()['officeNetworks']);
		}
	}//end testTheAdminSettingsStoreAndShowTheOfficeNetworks()
}//end class
