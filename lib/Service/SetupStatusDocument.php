<?php

/**
 * SetupStatusDocument
 *
 * The setup status document the setup wizard reads.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Kernel\Contracts\DemoDataServiceInterface;
use OCP\IAppConfig;

/**
 * Builds what `GET /api/setup/status` answers. The workspace page carries the
 * same document for an admin (REQ-INIT-010), so both read it here.
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */
class SetupStatusDocument {
	/**
	 * The setup flow's version; bump it when the steps change.
	 *
	 * @var int
	 */
	public const SETUP_VERSION = 1;

	/**
	 * App-config key recording that the demo-data step has been ANSWERED
	 * (loaded or declined). See SetupController for why declining counts.
	 *
	 * @var string
	 */
	public const DEMO_DECIDED_KEY = 'demo_data_decided';

	/**
	 * App-config key holding the dataset the operator picked.
	 *
	 * @var string
	 */
	public const DATASET_KEY = 'demo_dataset';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig               $appConfig       Holds the demo-data decision and pick.
	 * @param DemoDataServiceInterface $demoDataService Lists the datasets the choice step offers.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly DemoDataServiceInterface $demoDataService,
	) {
	}//end __construct()

	/**
	 * The status document.
	 *
	 * `completed` is deliberately TRUE: this app declares no REQUIRED step, so
	 * setup must never gate the app. The demo-data step is reported so the
	 * wizard can stop asking once it has an answer.
	 *
	 * @return array<string, mixed> The document.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 * @spec openspec/changes/wizard-dataset-card-load/specs/first-time-setup/spec.md
	 */
	public function build(): array {
		$demoDecided = $this->appConfig->getValueString(Application::APP_ID, self::DEMO_DECIDED_KEY, '') !== '';
		$picked      = $this->appConfig->getValueString(Application::APP_ID, self::DATASET_KEY, '');

		return [
			'version'   => self::SETUP_VERSION,
			'completed' => true,
			// The choice step reads its options from here: it declares
			// `optionsSource: datasets` and no options of its own, so a
			// dataset missing from this list is a dataset nobody can pick.
			'datasets'  => $this->demoDataService->listChoices(),
			// Every id of `manifest.setup.steps`, asserted by the status
			// contract test: a step the server never reports stays open,
			// and an open step reopens the wizard on every page.
			'steps'     => [
				'welcome' => ['done' => true],
				// Answered once a card was picked ("None" included) or a load
				// ran. A pick without a load still counts: a wizard that
				// predates `loadAction` can only record the pick.
				'demo-data' => ['done' => ($demoDecided === true || $picked !== '')],
				'done' => ['done' => true],
			],
		];
	}//end build()
}//end class
