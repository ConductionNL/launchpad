<?php

/**
 * CidrIpFactory
 *
 * An OCP\Security\Ip\IFactory for unit tests without a Nextcloud server:
 * real CIDR matching with inet_pton for IPv4 and IPv6. The server's own
 * factory (IPLib) is exercised by tests/Unit/Database/OfficeNetworkLiveTest.php
 * where a live Nextcloud is booted.
 *
 * @category  Test
 * @package   Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use InvalidArgumentException;
use OCP\Security\Ip\IAddress;
use OCP\Security\Ip\IFactory;
use OCP\Security\Ip\IRange;

/**
 * CIDR factory for tests.
 */
class CidrIpFactory implements IFactory {
	/**
	 * Parse a range such as 10.0.0.0/8, 2001:db8::/32 or a single address.
	 *
	 * @param string $range The range.
	 *
	 * @return IRange
	 */
	public function rangeFromString(string $range): IRange {
		[$ip, $bits] = array_pad(explode('/', $range, 2), 2, null);
		$packed = @inet_pton((string)$ip);
		$max = $packed === false ? 0 : strlen($packed) * 8;
		if ($packed === false || ($bits !== null && (ctype_digit($bits) === false || (int)$bits > $max))) {
			throw new InvalidArgumentException('Given range can’t be parsed');
		}

		$prefix = $bits === null ? $max : (int)$bits;
		return new class($packed, $prefix, $range) implements IRange {
			public function __construct(private string $packed, private int $prefix, private string $text) {
			}

			public static function isValid(string $range): bool {
				return true;
			}

			public function contains(IAddress $address): bool {
				$other = inet_pton((string)$address);
				if ($other === false || strlen($other) !== strlen($this->packed)) {
					return false;
				}

				$bytes = intdiv($this->prefix, 8);
				if (substr($other, 0, $bytes) !== substr($this->packed, 0, $bytes)) {
					return false;
				}

				$rest = $this->prefix % 8;
				if ($rest === 0) {
					return true;
				}

				$mask = (0xFF << (8 - $rest)) & 0xFF;
				return (ord($other[$bytes]) & $mask) === (ord($this->packed[$bytes]) & $mask);
			}

			public function __toString(): string {
				return $this->text;
			}
		};
	}//end rangeFromString()

	/**
	 * Parse an address.
	 *
	 * @param string $ip The address.
	 *
	 * @return IAddress
	 */
	public function addressFromString(string $ip): IAddress {
		if (@inet_pton($ip) === false) {
			throw new InvalidArgumentException('Given address can’t be parsed');
		}

		return new class($ip) implements IAddress {
			public function __construct(private string $ip) {
			}

			public static function isValid(string $ip): bool {
				return true;
			}

			public function matches(IRange ... $ranges): bool {
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
	}//end addressFromString()
}//end class
