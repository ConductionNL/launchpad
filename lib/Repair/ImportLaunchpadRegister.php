<?php

/**
 * ImportLaunchpadRegister
 *
 * Creates the `launchpad` register and `dashboard` schema in OpenRegister from
 * `lib/Settings/launchpad_register.json`, on install and after every upgrade.
 *
 * WHY THIS DID NOT EXIST, AND WHAT IT BROKE.
 *
 * Nothing in this app ever imported that descriptor. Not a repair step, not a
 * migration, not the bootstrap — the file shipped and was never read. So on a
 * FRESH install the `launchpad` register was never created, and every
 * OpenRegister-backed feature failed: the v2 runtime manifest
 * (`GET /api/manifest`) answered with an empty `pages` array, and any attempt to
 * write a dashboard object returned `Register not found: 'launchpad'`.
 *
 * The reason it went unnoticed is that developer instances have the register —
 * imported by hand, or through OpenRegister's admin UI, at some point in the
 * past. `Version002000Date20260519000000` reinforces the illusion: it COPIES
 * existing dashboard rows into OpenRegister and therefore assumes the register
 * already exists, silently skipping when OpenRegister is absent. So the code
 * reads as though provisioning is handled somewhere, and it never was.
 *
 * The descriptor was also incomplete: it declared `components.schemas.Dashboard`
 * but no `components.registers` block at all, so importing it as-shipped would
 * have created the schema and still left `Register not found`. The register is
 * now declared alongside the schema.
 *
 * Found by the Playwright gate on its first run — the failure mode is invisible
 * to unit tests, since they never provision an instance.
 *
 * SAFE FOR A REPAIR STEP. Repair steps run with NO user session, and
 * OpenRegister's RBAC refuses unauthenticated writes — the usual trap for
 * IRepairStep seeding. `ConfigurationService::importFromApp()` is not subject to
 * it: it wraps the whole import in `SystemOperationContext::run()`, which is
 * exactly why OpenRegister uses the same call from its own `ImportFlowRegister`
 * step. This mirrors that step deliberately rather than inventing a second
 * provisioning path.
 *
 * @category  Repair
 * @package   OCA\LaunchPad\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Import the LaunchPad register descriptor into OpenRegister.
 */
class ImportLaunchpadRegister implements IRepairStep {
	/**
	 * App-relative path to the register descriptor.
	 *
	 * @var string
	 */
	private const REGISTER_PATH = '/lib/Settings/launchpad_register.json';

	/**
	 * Descriptor version handed to the importer's version_compare gate.
	 *
	 * Kept in step with `info.version` in the descriptor. The import is also
	 * content-authoritative for schemas — OpenRegister's
	 * `ImportHandler::schemaContentDiffers()` compares properties, required and
	 * authorization — so a schema edit still applies even when this version has
	 * not moved.
	 *
	 * @var string
	 */
	private const REGISTER_VERSION = '1.0.1';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's
	 *                                      ConfigurationService lazily, by string,
	 *                                      so this file holds no compile-time
	 *                                      reference to a class that may be absent.
	 * @param IAppManager $appManager Resolves this app's path on disk.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly IAppManager $appManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Human-readable step name shown by `occ upgrade`.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'Import the LaunchPad register (launchpad register + dashboard schema)';
	}//end getName()

	/**
	 * Import the descriptor, degrading to a warning on any failure.
	 *
	 * Never throws. OpenRegister is an optional dependency — an instance without
	 * it must still install launchpad, it simply has no OR-backed dashboards. An
	 * exception here would abort the whole upgrade.
	 *
	 * @param IOutput $output Output interface for status messages.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/runtime-shell/spec.md
	 */
	public function run(IOutput $output): void {
		try {
			$configurationService = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
		} catch (Throwable $e) {
			$output->info('LaunchPad: OpenRegister not available — skipping register import.');
			return;
		}

		try {
			$path = $this->appManager->getAppPath('launchpad') . self::REGISTER_PATH;
			if (is_file($path) === false) {
				$output->warning('LaunchPad register descriptor not found: ' . $path);
				return;
			}

			// The importer takes the DECODED descriptor. Its sibling
			// importFromFilePath() expects a Nextcloud-root-relative path and
			// would fail closed on this absolute one.
			$data = json_decode((string)file_get_contents($path), true);
			if (is_array($data) === false) {
				$output->warning('LaunchPad register descriptor is not valid JSON: ' . $path);
				return;
			}

			$configurationService->importFromApp(
				appId: 'launchpad',
				data: $data,
				version: self::REGISTER_VERSION,
				force: false
			);

			$output->info('LaunchPad register imported (launchpad register + dashboard schema)');
		} catch (Throwable $e) {
			$this->logger->warning('[ImportLaunchpadRegister] import failed: ' . $e->getMessage());
			$output->warning('LaunchPad register import skipped: ' . $e->getMessage());
		}//end try
	}//end run()
}//end class
