<?php

/**
 * Initialize Actions Repair Step
 *
 * Seeds the ADR-023 action-authorization matrix on fresh install if empty.
 * Preserves any admin-customized matrix on upgrade — existing non-empty
 * matrix values are left untouched.
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

use OCA\LaunchPad\Service\ActionAuthService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Seed the action-authorization matrix from lib/actions.seed.json on install.
 *
 * @spec openspec/architecture/adr-023-action-authorization.md
 */
class InitializeActions implements IRepairStep {
	private const SEED_PATH = __DIR__ . '/../actions.seed.json';

	/**
	 * Constructor.
	 *
	 * @param ActionAuthService $actionAuth The action authorization service.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct(
		private ActionAuthService $actionAuth,
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
		return 'Initialize action-authorization matrix (ADR-023)';
	}//end getName()

	/**
	 * Seed the matrix if empty; preserve any existing admin-customized matrix.
	 *
	 * @param IOutput $output Repair output channel.
	 *
	 * @return void
	 *
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	public function run(IOutput $output): void {
		$existing = $this->actionAuth->getMatrix();
		if (count($existing) > 0) {
			$output->info($this->preservingLine(entries: count($existing)));
			return;
		}

		$actions = $this->readSeedActions(output: $output);
		if ($actions === null) {
			return;
		}

		try {
			$this->actionAuth->setMatrix(matrix: $actions);
		} catch (\JsonException $e) {
			$output->warning('Failed to write matrix: ' . $e->getMessage());
			return;
		}

		$output->info($this->seededLine(actions: count($actions)));

	}//end run()

	/**
	 * The `actions` object of actions.seed.json; NULL (reported) when the file
	 * is missing, unreadable, malformed or has no `actions` object.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return array<string, mixed>|null The seed actions.
	 */
	private function readSeedActions(IOutput $output): array|null {
		if (file_exists(self::SEED_PATH) === false) {
			$output->warning('actions.seed.json not found — matrix left empty (default-deny).');
			$this->logger->warning('[launchpad] ADR-023 seed file missing at ' . self::SEED_PATH);
			return null;
		}

		$raw = file_get_contents(self::SEED_PATH);
		if ($raw === false) {
			$output->warning('Could not read actions.seed.json — matrix left empty (default-deny).');
			return null;
		}

		return $this->decodeSeedActions(output: $output, raw: $raw);
	}//end readSeedActions()

	/**
	 * The `actions` object of the seed JSON; NULL (reported) when malformed or absent.
	 *
	 * @param IOutput $output The repair output.
	 * @param string  $raw    The seed file contents.
	 *
	 * @return array<string, mixed>|null The seed actions.
	 */
	private function decodeSeedActions(IOutput $output, string $raw): array|null {
		try {
			$parsed = json_decode($raw, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$output->warning('actions.seed.json invalid JSON: ' . $e->getMessage());
			$this->logger->error('[launchpad] ADR-023 seed malformed: ' . $e->getMessage());
			return null;
		}

		$actions = ($parsed['actions'] ?? null);
		if (is_array($actions) === false) {
			$output->warning('actions.seed.json missing `actions` object — matrix left empty.');
			return null;
		}

		return $actions;
	}//end decodeSeedActions()

	/**
	 * The line reported when a matrix already exists.
	 *
	 * @param int $entries The number of stored entries.
	 *
	 * @return string
	 */
	private function preservingLine(int $entries): string {
		$entrySuffix = 'ies';
		if ($entries === 1) {
			$entrySuffix = 'y';
		}

		return sprintf(
			'Action matrix already has %d entr%s — preserving.',
			$entries,
			$entrySuffix
		);
	}//end preservingLine()

	/**
	 * The line reported after seeding.
	 *
	 * @param int $actions The number of seeded actions.
	 *
	 * @return string
	 */
	private function seededLine(int $actions): string {
		$actionSuffix = 's';
		if ($actions === 1) {
			$actionSuffix = '';
		}

		return sprintf(
			'Seeded action matrix with %d action%s (default: admin-only).',
			$actions,
			$actionSuffix
		);
	}//end seededLine()
}//end class
