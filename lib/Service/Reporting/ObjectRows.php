<?php

/**
 * ObjectRows
 *
 * Reads objects from OpenRegister for the shipped reports, through its
 * published object service, with the caller's own access applied.
 *
 * Launchpad runs without OpenRegister, so the service is looked up by name at
 * call time and never imported: on an instance without it every read answers
 * null, which a report shows as "unavailable" rather than as an error.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\Reporting
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Reporting;

use JsonSerializable;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Object reads for reports, null when OpenRegister or the schema is not there.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class ObjectRows {

	/**
	 * OpenRegister's published object service, held as a string so nothing loads it on an instance without OpenRegister.
	 */
	public const OBJECT_SERVICE = 'OCA\\OpenRegister\\Contract\\ObjectServiceInterface';

	/**
	 * The most rows one report reads.
	 */
	public const MAX_ROWS = 5000;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's object service at call time.
	 * @param LoggerInterface    $logger    Logs a read that failed.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The objects of one schema in one register, as arrays, or null when they cannot be read.
	 *
	 * @param string               $register The register slug.
	 * @param string               $schema   The schema slug.
	 * @param array<string, mixed> $filters  Property filters, passed to the search as they are.
	 * @param int                  $limit    The most rows to read, capped at MAX_ROWS.
	 *
	 * @return list<array<string, mixed>>|null
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function rows(string $register, string $schema, array $filters=[], int $limit=self::MAX_ROWS): ?array {
		$service = $this->service();
		if ($service === null || $register === '' || $schema === '') {
			return null;
		}

		$query = array_merge(
			$filters,
			[
				'@self'  => ['register' => $register, 'schema' => $schema],
				'_limit' => max(1, min($limit, self::MAX_ROWS)),
			]
		);

		try {
			$found = $service->searchObjects(query: $query);
		} catch (Throwable $e) {
			$this->logger->info(
				message: 'launchpad.report.read_failed',
				context: ['register' => $register, 'schema' => $schema, 'exception' => $e->getMessage()]
			);
			return null;
		}

		if (is_array($found) === false) {
			return null;
		}

		$rows = [];
		foreach ($found as $object) {
			$row = $this->toArray(object: $object);
			if ($row !== null) {
				$rows[] = $row;
			}
		}

		return $rows;
	}//end rows()

	/**
	 * Whether OpenRegister's object service is installed.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function isInstalled(): bool {
		return ($this->service() !== null);
	}//end isInstalled()

	/**
	 * The object service, or null without OpenRegister.
	 *
	 * @return object|null
	 */
	private function service(): ?object {
		try {
			if ($this->container->has(self::OBJECT_SERVICE) === false) {
				return null;
			}

			$service = $this->container->get(self::OBJECT_SERVICE);
		} catch (Throwable) {
			return null;
		}

		if (is_object($service) === false) {
			return null;
		}

		return $service;
	}//end service()

	/**
	 * One search result as an array.
	 *
	 * @param mixed $object An entity or an array.
	 *
	 * @return array<string, mixed>|null
	 */
	private function toArray(mixed $object): ?array {
		if (is_array($object) === true) {
			return $object;
		}

		if ($object instanceof JsonSerializable) {
			$data = $object->jsonSerialize();
			if (is_array($data) === true) {
				return $data;
			}
		}

		return null;
	}//end toArray()
}//end class
