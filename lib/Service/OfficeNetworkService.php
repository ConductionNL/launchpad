<?php

/**
 * OfficeNetworkService
 *
 * Whether a request comes from one of the office networks an administrator
 * listed, so a tile can open its office-network address there.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCP\IRequest;
use OCP\Security\Ip\IFactory;
use Psr\Log\LoggerInterface;

/**
 * The server decides from the request address (design D1): the browser never
 * probes hosts. `IRequest::getRemoteAddress()` honours Nextcloud's trusted
 * proxies and forwarded-for headers. No ranges listed means never on the
 * office network, so nothing changes until an administrator sets them.
 *
 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
 */
class OfficeNetworkService {

	/**
	 * Constructor.
	 *
	 * @param IRequest           $request       The current request.
	 * @param IFactory           $ipFactory     Parses addresses and CIDR ranges.
	 * @param AdminSettingMapper $settingMapper Reads the stored ranges.
	 * @param LoggerInterface    $logger        Reports a stored range that no longer parses.
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
	 */
	public function __construct(
		private readonly IRequest $request,
		private readonly IFactory $ipFactory,
		private readonly AdminSettingMapper $settingMapper,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether the current request comes from inside the office networks.
	 *
	 * @return bool True when its address falls in one of the stored ranges.
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
	 */
	public function isOfficeRequest(): bool {
		return $this->isInside(address: $this->currentAddress(), ranges: $this->storedRanges());
	}//end isOfficeRequest()

	/**
	 * The current request's address, as Nextcloud resolves it.
	 *
	 * @return string The address; empty when there is none.
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-administrators-declare-the-office-networks-req-tia-001
	 */
	public function currentAddress(): string {
		return $this->request->getRemoteAddress();
	}//end currentAddress()

	/**
	 * Whether an address falls in one of the ranges; a range that does not parse is skipped and logged.
	 *
	 * @param string            $address The address.
	 * @param array<int,string> $ranges  CIDR ranges.
	 *
	 * @return bool True when one range contains it.
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
	 */
	public function isInside(string $address, array $ranges): bool {
		if ($ranges === [] || $address === '') {
			return false;
		}

		try {
			$parsed = $this->ipFactory->addressFromString($address);
		} catch (InvalidArgumentException) {
			return false;
		}

		foreach ($ranges as $range) {
			try {
				if ($this->ipFactory->rangeFromString((string)$range)->contains($parsed) === true) {
					return true;
				}
			} catch (InvalidArgumentException) {
				$this->logger->warning('LaunchPad: a stored office network range does not parse and is ignored.', ['range' => $range]);
			}
		}

		return false;
	}//end isInside()

	/**
	 * Check and normalise a list of ranges an administrator entered.
	 *
	 * @param array<mixed> $ranges The entered ranges.
	 *
	 * @return array<int,string> The trimmed ranges, blanks and duplicates left out.
	 *
	 * @throws InvalidArgumentException When one is not a valid IPv4 or IPv6 CIDR range; nothing is stored then.
	 *
	 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-administrators-declare-the-office-networks-req-tia-001
	 */
	public function normaliseRanges(array $ranges): array {
		$clean = [];
		foreach ($ranges as $range) {
			if (is_string($range) === false) {
				throw new InvalidArgumentException(message: 'This is not a valid network range');
			}

			$range = trim($range);
			if ($range === '') {
				continue;
			}

			// A bare address is not a range: the administrator must say how wide the network is.
			if (str_contains(haystack: $range, needle: '/') === false) {
				throw new InvalidArgumentException(message: 'This is not a valid network range: ' . $range);
			}

			try {
				$this->ipFactory->rangeFromString($range);
			} catch (InvalidArgumentException) {
				throw new InvalidArgumentException(message: 'This is not a valid network range: ' . $range);
			}

			if (in_array(needle: $range, haystack: $clean, strict: true) === false) {
				$clean[] = $range;
			}
		}//end foreach

		return $clean;
	}//end normaliseRanges()

	/**
	 * The ranges an administrator stored.
	 *
	 * @return array<int,string>
	 */
	private function storedRanges(): array {
		$stored = ($this->settingMapper->getAllAsArray()[AdminSetting::KEY_OFFICE_NETWORKS] ?? []);
		if (is_array($stored) === false) {
			return [];
		}

		return array_values(array_filter($stored, 'is_string'));
	}//end storedRanges()
}//end class
