<?php

/**
 * Apply Action Baseline Repair Step
 *
 * Broadens the ADR-023 action-authorization matrix on an ALREADY-INSTALLED
 * instance so the ordinary end-user surface is reachable by non-admins.
 *
 * `InitializeActions` only seeds when the matrix is empty, so every instance
 * installed before the baseline shipped is stuck on the old
 * "every action => [\"admin\"]" seed: the first AJAX call a non-admin session
 * makes fails with `Action 'dashboard.list' requires admin rights`, and the
 * app is admin-only until somebody hand-edits the matrix. This step fixes
 * those instances without trampling deliberate admin customisation: an entry
 * is only rewritten when it still holds the pristine `["admin"]` default (or
 * is absent entirely). Anything an admin has touched is left alone.
 *
 * Runs at most once per baseline version — the version marker is WRITTEN
 * after a successful pass, so the step is idempotent and an admin who
 * deliberately narrows an action back down to admin-only does not get it
 * re-broadened on the next upgrade.
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/architecture/adr-023-action-authorization.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Broaden pristine admin-only matrix entries to the shipped non-admin baseline.
 *
 * @spec openspec/architecture/adr-023-action-authorization.md
 */
class ApplyActionBaseline implements IRepairStep {
	/**
	 * Path to the shipped seed, which is the single source of truth for
	 * which actions belong to the non-admin baseline.
	 */
	private const SEED_PATH = __DIR__ . '/../actions.seed.json';

	/**
	 * App-config key recording the baseline version already applied.
	 *
	 * MUST be written whenever the step completes — a version gate whose
	 * key is never written is not a gate, it just re-runs forever.
	 */
	private const VERSION_KEY = 'actions_baseline_version';

	/**
	 * The baseline revision this build ships. Bump when the shipped
	 * baseline changes AND the change should reach existing instances.
	 */
	private const BASELINE_VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @param ActionAuthService $actionAuth The action authorization service.
	 * @param IAppConfig $appConfig App config, for the version marker.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct(
		private ActionAuthService $actionAuth,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Repair-step name.
	 *
	 * @return string
	 *
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	public function getName(): string {
		return 'Apply the non-admin action-authorization baseline (ADR-023)';
	}//end getName()

	/**
	 * Broaden pristine admin-only entries to the shipped baseline.
	 *
	 * @param IOutput $output Repair output channel.
	 *
	 * @return void
	 *
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	public function run(IOutput $output): void {
		$applied = $this->appConfig->getValueInt(
			Application::APP_ID,
			self::VERSION_KEY,
			0
		);
		if ($applied >= self::BASELINE_VERSION) {
			$output->info(
				sprintf(
					'Action baseline v%d already applied — nothing to do.',
					$applied
				)
			);
			return;
		}

		$baseline = $this->readBaseline();
		if (count($baseline) === 0) {
			$output->warning(
				'actions.seed.json unreadable or declares no baseline actions — matrix left unchanged.'
			);
			$this->logger->warning(
				'[launchpad] ADR-023 baseline seed unreadable at ' . self::SEED_PATH
			);
			return;
		}

		$matrix = $this->actionAuth->getMatrix();
		$broadened = 0;
		$preserved = 0;

		foreach ($baseline as $action => $groups) {
			$current = ($matrix[$action] ?? null);

			// Only touch entries that still hold the pristine admin-only
			// default (or that predate this action existing at all).
			// Anything else is an admin decision and stays put.
			if ($current !== null && $current !== ['admin']) {
				$preserved++;
				continue;
			}

			$matrix[$action] = $groups;
			$broadened++;
		}

		if ($broadened > 0) {
			try {
				$this->actionAuth->setMatrix(matrix: $matrix);
			} catch (\JsonException $e) {
				$output->warning('Failed to write matrix: ' . $e->getMessage());
				$this->logger->error(
					'[launchpad] ADR-023 baseline write failed: ' . $e->getMessage()
				);
				return;
			}
		}

		// Write the marker LAST and only on a successful pass, so a failed
		// write is retried on the next upgrade instead of being recorded
		// as done.
		$this->appConfig->setValueInt(
			Application::APP_ID,
			self::VERSION_KEY,
			self::BASELINE_VERSION
		);

		$preservedSuffix = 'ies';
		if ($preserved === 1) {
			$preservedSuffix = 'y';
		}

		$output->info(
			sprintf(
				'Action baseline v%d applied: %d action(s) broadened to the non-admin baseline, %d admin-customized entr%s preserved.',
				self::BASELINE_VERSION,
				$broadened,
				$preserved,
				$preservedSuffix
			)
		);

	}//end run()

	/**
	 * Read the baseline entries from the shipped seed.
	 *
	 * The baseline is exactly the set of seed entries carrying the
	 * {@see ActionAuthService::GROUP_ALL_USERS} sentinel — keeping the seed
	 * file the single source of truth means an install and an upgrade can
	 * never disagree about what "the baseline" is.
	 *
	 * @return array<string, array<int, string>> Map of action => allowed groups.
	 */
	private function readBaseline(): array {
		$actions = $this->readSeedActions();

		$baseline = [];
		foreach ($actions as $action => $groups) {
			if (is_string($action) === false || is_array($groups) === false) {
				continue;
			}

			if (in_array(ActionAuthService::GROUP_ALL_USERS, $groups, true) === false) {
				continue;
			}

			$baseline[$action] = $this->normaliseGroups(groups: $groups);
		}

		return $baseline;
	}//end readBaseline()

	/**
	 * Read and decode the `actions` map out of the seed file.
	 *
	 * A missing, unreadable or malformed seed yields an empty map so the
	 * repair step degrades to a no-op rather than aborting the upgrade.
	 *
	 * @return array<mixed> The raw `actions` map, or an empty array.
	 */
	private function readSeedActions(): array {
		if (file_exists(self::SEED_PATH) === false) {
			return [];
		}

		$raw = file_get_contents(self::SEED_PATH);
		if ($raw === false) {
			return [];
		}

		try {
			$parsed = json_decode($raw, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$this->logger->error(
				'[launchpad] ADR-023 baseline seed malformed: ' . $e->getMessage()
			);
			return [];
		}

		$actions = ($parsed['actions'] ?? null);
		if (is_array($actions) === false) {
			return [];
		}

		return $actions;
	}//end readSeedActions()

	/**
	 * Reduce a seed group list to unique, non-empty strings.
	 *
	 * @param array<mixed> $groups The raw group list from the seed file.
	 *
	 * @return array<int, string> The cleaned, de-duplicated group list.
	 */
	private function normaliseGroups(array $groups): array {
		$clean = [];
		foreach ($groups as $group) {
			if (is_string($group) === true && $group !== '') {
				$clean[] = $group;
			}
		}

		return array_values(array_unique($clean));
	}//end normaliseGroups()
}//end class
