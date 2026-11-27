<?php
/**
 * KeyContainerChildren
 *
 * Repair step: every child of a stored well (container) or tabs widget gets a
 * key of its own, so an embed token can name it (REQ-WDG-050). New content is
 * keyed as it is written (WidgetPlacement::setContentArray).
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

use OCA\LaunchPad\Db\ContainerChildKeys;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Key the children of every stored well and tabs widget. Idempotent: a widget
 * whose children all have a key is not written again.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
class KeyContainerChildren implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placements The placement store.
	 * @param LoggerInterface       $logger     PSR-3 logger.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placements,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function getName(): string {
		return 'Give every widget inside a well or tabs widget a key of its own';
	}//end getName()

	/**
	 * Run the step. A failure is a warning, never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function run(IOutput $output): void {
		$keyed = 0;
		$keys  = new ContainerChildKeys();
		try {
			foreach (['container', 'tabs'] as $type) {
				foreach ($this->placements->findByWidgetId(widgetId: $type) as $placement) {
					$content = $placement->getContentArray();
					$ownerId = null;
					if (is_int($placement->getId()) === true) {
						$ownerId = $placement->getId();
					}

					if ($keys->stamp(content: $content, ownerId: $ownerId) === $content) {
						continue;
					}

					$placement->setContentArray(content: $content);
					$this->placements->update(entity: $placement);
					$keyed++;
				}
			}
		} catch (Throwable $e) {
			$this->logger->warning(message: '[KeyContainerChildren] could not key the children of wells: ' . $e->getMessage());
			$output->warning(message: 'The widgets inside wells were not given keys: ' . $e->getMessage());
			return;
		}

		$output->info(message: sprintf('%d well(s) or tabs widget(s) got keys for their widgets.', $keyed));
	}//end run()
}//end class
