<?php

/**
 * MergeAllowedSites
 *
 * Repair step: merge the per-feature host allow-lists into the one allowed
 * websites list with the purpose each had, once (REQ-ASET-021).
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AllowedSitesService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\HealthPingService;
use OCA\LaunchPad\Service\HostAllowList;
use OCA\LaunchPad\Service\IframeService;
use OCA\LaunchPad\Service\ImageHostService;
use OCA\LaunchPad\Service\LiveTileService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Copy the hosts of the embed, image, live tile, health ping and calendar
 * feed lists into `allowed_sites`, so nothing that worked stops working.
 * The per-feature lists are kept as they are.
 *
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
class MergeAllowedSites implements IRepairStep {

	/**
	 * IAppConfig key set once the merge ran, so it runs only once.
	 *
	 * @var string
	 */
	public const DONE_KEY = 'allowed_sites_merged';

	/**
	 * The per-feature list keys and the purpose their hosts get.
	 *
	 * @var array<string, string>
	 */
	public const LEGACY_LISTS = [
		IframeService::CONFIG_KEY_ALLOWED_HOSTS         => AllowedSitesService::PURPOSE_EMBED,
		ImageHostService::CONFIG_KEY_ALLOWED_HOSTS      => AllowedSitesService::PURPOSE_EMBED,
		LiveTileService::CONFIG_KEY_ALLOWED_HOSTS       => AllowedSitesService::PURPOSE_FETCH,
		HealthPingService::CONFIG_KEY_ALLOWED_HOSTS     => AllowedSitesService::PURPOSE_FETCH,
		CalendarWidgetService::CONFIG_KEY_ALLOWED_HOSTS => AllowedSitesService::PURPOSE_FETCH,
	];

	/**
	 * Constructor.
	 *
	 * @param IAppConfig          $appConfig    Holds the lists.
	 * @param AllowedSitesService $allowedSites Writes the merged list.
	 * @param LoggerInterface     $logger       PSR-3 logger.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly AllowedSitesService $allowedSites,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function getName(): string {
		return 'Merge the per-feature host lists into the allowed websites list';
	}//end getName()

	/**
	 * Run the step once. A failure is a warning, never a failed upgrade, and
	 * leaves the step to run again on the next upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function run(IOutput $output): void {
		if ($this->appConfig->getValueString(app: Application::APP_ID, key: self::DONE_KEY, default: '') === '1') {
			return;
		}

		try {
			$added = 0;
			foreach (self::LEGACY_LISTS as $key => $purpose) {
				$hosts  = (new HostAllowList(appConfig: $this->appConfig, configKey: $key))->hosts();
				$added += $this->allowedSites->addHosts(hosts: $hosts, purpose: $purpose);
			}

			$this->appConfig->setValueString(app: Application::APP_ID, key: self::DONE_KEY, value: '1');
		} catch (Throwable $e) {
			$this->logger->warning(message: '[MergeAllowedSites] could not merge the host lists: ' . $e->getMessage());
			$output->warning(message: 'The host lists were not merged into the allowed websites: ' . $e->getMessage());
			return;
		}

		$output->info(message: sprintf('%d host(s) merged into the allowed websites list.', $added));
	}//end run()
}//end class
