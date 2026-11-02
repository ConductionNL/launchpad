<?php

/**
 * OfficeNetworkService
 *
 * The office networks an administrator lists as IP ranges, and whether the
 * current request comes from one (launcher-tile-internal-address, REQ-TIA-001).
 * The server decides from the request address, so the browser never probes
 * internal hosts. An empty list means no request is on the office network.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCP\IRequest;
use OCP\Security\Ip\IFactory;
use Throwable;

/**
 * Office network ranges and the request check.
 *
 * @spec openspec/specs/tiles/spec.md
 */
class OfficeNetworkService {
	/**
	 * Most ranges an administrator may list.
	 *
	 * @var int
	 */
	public const MAX_RANGES = 50;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settingMapper Admin settings store.
	 * @param IRequest $request The current request.
	 * @param IFactory $ipFactory Parses addresses and ranges.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function __construct(
		private readonly AdminSettingMapper $settingMapper,
		private readonly IRequest $request,
		private readonly IFactory $ipFactory,
	) {
	}//end __construct()

	/**
	 * The stored ranges; an unparsable stored range is left out.
	 *
	 * @return string[]
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function getRanges(): array {
		$raw = $this->settingMapper->getValue(key: AdminSettingKey::OFFICE_NETWORKS->value, default: []);
		if (is_array(value: $raw) === false) {
			return [];
		}

		$ranges = [];
		foreach ($raw as $range) {
			if (is_string(value: $range) === true && $this->isValidRange(range: $range) === true) {
				$ranges[] = $range;
			}
		}

		return $ranges;
	}//end getRanges()

	/**
	 * Validate and store the ranges (IPv4 or IPv6 CIDR, or a single address).
	 *
	 * @param array $raw The ranges.
	 *
	 * @return string[] The stored ranges.
	 *
	 * @throws InvalidArgumentException When a range is malformed or there are too many.
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function saveRanges(array $raw): array {
		$ranges = [];
		foreach ($raw as $range) {
			$range = trim(string: (string)$range);
			if ($range === '') {
				continue;
			}

			if ($this->isValidRange(range: $range) === false) {
				throw new InvalidArgumentException(message: 'This is not a valid network range: ' . mb_substr(string: $range, start: 0, length: 64));
			}

			$ranges[$range] = $range;
		}

		if (count(value: $ranges) > self::MAX_RANGES) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_RANGES . ' office network ranges');
		}

		$ranges = array_values(array: $ranges);
		$this->settingMapper->setSetting(key: AdminSettingKey::OFFICE_NETWORKS->value, value: $ranges);

		return $ranges;
	}//end saveRanges()

	/**
	 * Whether the current request comes from an office network.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function isOfficeRequest(): bool {
		$ranges = $this->getRanges();
		if ($ranges === []) {
			return false;
		}

		try {
			$address = $this->ipFactory->addressFromString(ip: $this->currentAddress());
		} catch (Throwable) {
			return false;
		}

		foreach ($ranges as $range) {
			if ($this->ipFactory->rangeFromString(range: $range)->contains(address: $address) === true) {
				return true;
			}
		}

		return false;
	}//end isOfficeRequest()

	/**
	 * The address the current request comes from, as Nextcloud sees it
	 * (trusted proxies applied).
	 *
	 * @return string
	 *
	 * @spec openspec/specs/tiles/spec.md
	 */
	public function currentAddress(): string {
		return $this->request->getRemoteAddress();
	}//end currentAddress()

	/**
	 * Whether a string parses as a range.
	 *
	 * @param string $range The candidate.
	 *
	 * @return bool
	 */
	private function isValidRange(string $range): bool {
		try {
			$this->ipFactory->rangeFromString(range: $range);
			return true;
		} catch (Throwable) {
			return false;
		}
	}//end isValidRange()
}//end class
