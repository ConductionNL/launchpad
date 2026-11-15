<?php

/**
 * StatusReportStore
 *
 * Keeps a status report as an OpenRegister object (REQ-DWMS-004).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\StatusReport
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

namespace OCA\LaunchPad\Service\StatusReport;

use OCP\DB\QueryBuilder\IQueryBuilder;
use JsonSerializable;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes a kept status report to launchpad's register through OpenRegister's published object service.
 *
 * Nothing loads OpenRegister's classes on an instance without it: the
 * service and its option values are named as strings and resolved at call
 * time, and without them the store answers null.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-004-a-dated-status-report-with-a-health-rating
 */
class StatusReportStore {

	/**
	 * The register.
	 */
	public const REGISTER = 'launchpad';

	/**
	 * The schema.
	 */
	public const SCHEMA = 'status_report';

	/**
	 * OpenRegister's published object service.
	 */
	public const OBJECT_SERVICE = 'OCA\\OpenRegister\\Contract\\ObjectServiceInterface';

	/**
	 * The save options it takes.
	 */
	public const SAVE_OPTIONS = 'OCA\\OpenRegister\\Kernel\\Contracts\\Value\\ObjectSaveOptions';

	/**
	 * The register and schema a save names.
	 */
	public const FIND_TARGET = 'OCA\\OpenRegister\\Kernel\\Contracts\\Value\\ObjectFindTarget';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's object service at call time.
	 * @param LoggerInterface    $logger    Logs a save that failed.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.1
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Store the report; the stored object as an array, or null when it could not be stored.
	 *
	 * @param array<string,mixed> $report The report.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.1
	 */
	public function save(array $report): ?array {
		if (class_exists(self::SAVE_OPTIONS) === false || class_exists(self::FIND_TARGET) === false) {
			return null;
		}

		try {
			if ($this->container->has(self::OBJECT_SERVICE) === false) {
				return null;
			}

			$service = $this->container->get(self::OBJECT_SERVICE);
			$target  = new (self::FIND_TARGET)(register: self::REGISTER, schema: self::SCHEMA);
			$stored  = $service->saveObject(object: $report, options: new (self::SAVE_OPTIONS)(target: $target));
		} catch (Throwable $e) {
			$this->logger->warning(message: 'launchpad.status_report.save_failed', context: ['exception' => $e->getMessage()]);
			return null;
		}

		if ($stored instanceof JsonSerializable) {
			$stored = $stored->jsonSerialize();
		}

		if (is_array($stored) === false) {
			return null;
		}

		return $stored;
	}//end save()
}//end class
