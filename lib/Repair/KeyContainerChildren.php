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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Key the children of every stored well and tabs widget. Idempotent: a widget
 * whose children all have a key is not written again.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
class KeyContainerChildren implements IRepairStep {
	/**
	 * The table the widget placements live in.
	 */
	private const TABLE = 'launchpad_widget_placements';

	/**
	 * Stamps the keys.
	 *
	 * @var ContainerChildKeys
	 */
	private readonly ContainerChildKeys $keys;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection   $db     The database.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		private readonly IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
		$this->keys = new ContainerChildKeys();
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
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
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function run(IOutput $output): void {
		$keyed = 0;
		try {
			$select = $this->db->getQueryBuilder();
			$select->select('id', 'content')
				->from(self::TABLE)
				->where(
					$select->expr()->in(
						x: 'widget_id',
						y: $select->createNamedParameter(value: ['container', 'tabs'], type: IQueryBuilder::PARAM_STR_ARRAY)
					)
				);
			$rows = $select->executeQuery();
			while (($row = $rows->fetch()) !== false) {
				$keyed += $this->keyRow(id: (int)$row['id'], json: (string)($row['content'] ?? ''));
			}

			$rows->closeCursor();
		} catch (Throwable $e) {
			$this->logger->warning(message: '[KeyContainerChildren] could not key the children of wells: ' . $e->getMessage());
			$output->warning(message: 'The widgets inside wells were not given keys: ' . $e->getMessage());
			return;
		}

		$output->info(message: sprintf('%d well(s) or tabs widget(s) got keys for their widgets.', $keyed));
	}//end run()

	/**
	 * Key the children of one stored widget; 1 when it was written, else 0.
	 *
	 * @param int    $id   The placement id.
	 * @param string $json The stored content.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	private function keyRow(int $id, string $json): int {
		$content = json_decode(json: $json, associative: true);
		if (is_array($content) === false) {
			return 0;
		}

		$keyed = $this->keys->stamp(content: $content, ownerId: $id);
		if ($keyed === $content) {
			return 0;
		}

		$update = $this->db->getQueryBuilder();
		$update->update(update: self::TABLE)
			->set(key: 'content', value: $update->createNamedParameter(value: json_encode($keyed)))
			->where($update->expr()->eq(x: 'id', y: $update->createNamedParameter(value: $id, type: IQueryBuilder::PARAM_INT)));
		$update->executeStatement();
		return 1;
	}//end keyRow()
}//end class
