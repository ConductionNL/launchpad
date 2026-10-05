<?php

/**
 * ShippedTemplateUpdateService
 *
 * Brings an installed shipped template to the version LaunchPad ships now,
 * in place: the template keeps its id, UUID, name, groups and default flag,
 * and its widgets become the new version's. Members' copies then follow
 * through the existing re-sync ({@see TemplateResyncService}, `merge`).
 *
 * WHY THE ROWS ARE KEPT. A member's copy remembers, per widget, the id of the
 * template widget it was made from. Re-sync matches on that id. So a widget
 * that is in both versions keeps its row and is changed there; only a widget
 * the new version adds gets a new row, and only one it drops loses its row.
 * Replacing every row would still give members the right page, but each of
 * their widgets would be removed and added again.
 *
 * HOW A WIDGET IS RECOGNISED. A definition carries no key per widget, so the
 * installed widgets are paired with the new ones in three passes, each pass
 * only over what the pass before left: (1) every compared field equal, (2)
 * same widget type, same proxied Nextcloud widget and same title, (3) same
 * widget type, when exactly one is left on each side. What is then left of
 * the new version is added, what is left of the installed one is removed.
 *
 * NEVER SILENTLY. The result names every widget added, removed and changed,
 * and a dry run returns the same lists without writing anything.
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
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use RuntimeException;
use Throwable;

/**
 * Update an installed shipped template in place and bring its copies along.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */
class ShippedTemplateUpdateService {
	/**
	 * The plain fields a definition sets on a widget, and the word the
	 * report uses for a change in each. `styleConfig` and `content` are
	 * compared as decoded JSON, see {@see self::snapshot()}.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_WORDS = [
		'widgetId' => 'type',
		'gridX' => 'position',
		'gridY' => 'position',
		'gridWidth' => 'size',
		'gridHeight' => 'size',
		'isCompulsory' => 'compulsory',
		'isVisible' => 'visibility',
		'showTitle' => 'title',
		'sortOrder' => 'order',
		'customTitle' => 'title',
		'customIcon' => 'icon',
		'tileType' => 'tile',
		'tileTitle' => 'tile',
		'tileIcon' => 'tile',
		'tileIconType' => 'tile',
		'tileBackgroundColor' => 'tile',
		'tileTextColor' => 'tile',
		'tileLinkType' => 'tile',
		'tileLinkValue' => 'tile',
		'styleConfig' => 'style',
		'content' => 'settings',
	];

	/**
	 * Constructor.
	 *
	 * @param ShippedTemplateService   $shipped         Reads the definition and finds the install.
	 * @param DashboardMapper          $dashboardMapper Counts the members' copies.
	 * @param WidgetPlacementMapper    $placementMapper Reads and writes the template's widgets.
	 * @param PlacementPayloadHydrator $hydrator        Builds a widget from a definition, as the importer does.
	 * @param TemplateResyncService    $resyncService   Brings the members' copies along.
	 * @param IAppConfig               $appConfig       Records the installed version.
	 * @param IDBConnection            $db              One transaction for the template's widgets.
	 */
	public function __construct(
		private readonly ShippedTemplateService $shipped,
		private readonly DashboardMapper $dashboardMapper,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly PlacementPayloadHydrator $hydrator,
		private readonly TemplateResyncService $resyncService,
		private readonly IAppConfig $appConfig,
		private readonly IDBConnection $db,
	) {
	}//end __construct()

	/**
	 * Update the installed template to the shipped version, or say what
	 * that would change.
	 *
	 * Nothing happens when the installed version is the shipped one or
	 * newer: the result says so (`upToDate`) and its lists are empty.
	 *
	 * @param string $templateId The shipped template id.
	 * @param bool   $dryRun     Report only, write nothing.
	 * @param string $userId     Who runs the update (for the re-sync's audit record).
	 *
	 * @return array{templateId:string, uuid:string, id:int, name:string, installedVersion:int, version:int,
	 *               upToDate:bool, dryRun:bool, applied:bool, added:array<int,string>, removed:array<int,string>,
	 *               changed:array<int,array{widget:string, fields:array<int,string>}>, unchanged:int, copies:int,
	 *               resync:array{async:bool, affectedCount:int, totalCopies:int}|null}
	 *
	 * @throws \InvalidArgumentException When the id is not a shipped template.
	 * @throws TemplateNotInstalledException When the template is not installed here.
	 * @throws RuntimeException When the definition is malformed or the write fails.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
	 *      The flag is the command's `--dry-run` and the request's `dryRun`, one to one.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
	 */
	public function update(string $templateId, bool $dryRun = false, string $userId = 'cli'): array {
		$definition = $this->shipped->readDefinition(templateId: $templateId);
		$template = $this->shipped->findInstalled(templateId: $templateId);
		if ($template === null) {
			throw new TemplateNotInstalledException(
				message: 'Template ' . $templateId . ' is not installed here, so there is nothing to update.'
			);
		}

		$dashboardId = (int)$template->getId();
		$version = (int)$definition['templateVersion'];
		$installedVersion = $this->appConfig->getValueInt(
			Application::APP_ID,
			ShippedTemplateService::CONFIG_VERSION_PREFIX . $templateId,
			0
		);

		$result = [
			'templateId' => $templateId,
			'uuid' => (string)$template->getUuid(),
			'id' => $dashboardId,
			'name' => (string)$template->getName(),
			'installedVersion' => $installedVersion,
			'version' => $version,
			'upToDate' => $installedVersion >= $version,
			'dryRun' => $dryRun,
			'applied' => false,
			'added' => [],
			'removed' => [],
			'changed' => [],
			'unchanged' => 0,
			'copies' => count($this->dashboardMapper->findByBasedOnTemplate(templateId: $dashboardId)),
			'resync' => null,
		];
		if ($result['upToDate'] === true) {
			return $result;
		}

		$wanted = [];
		foreach ($definition['dashboard']['widgets'] as $payload) {
			$wanted[] = $this->hydrator->hydrate(dashboardId: $dashboardId, payload: (array)$payload, asTemplate: true);
		}

		$plan = $this->pair(
			installed: $this->placementMapper->findByDashboardId(dashboardId: $dashboardId),
			wanted: $wanted
		);
		$result = array_merge($result, $this->describe(plan: $plan));
		if ($dryRun === true) {
			return $result;
		}

		$this->apply(template: $template, plan: $plan);
		$this->appConfig->setValueInt(
			Application::APP_ID,
			ShippedTemplateService::CONFIG_VERSION_PREFIX . $templateId,
			$version
		);
		$result['applied'] = true;

		// Merge: a member's own widgets stay, every template widget is
		// brought in line, compulsory ones included (REQ-RESYNC-003, -004).
		$resync = $this->resyncService->resync(
			templateId: $dashboardId,
			strategy: TemplateResyncService::STRATEGY_MERGE,
			dryRun: false,
			actingAdminId: $userId
		);
		$result['resync'] = [
			'async' => ($resync['async'] ?? false) === true,
			'affectedCount' => (int)($resync['affectedCount'] ?? 0),
			'totalCopies' => (int)($resync['totalCopies'] ?? 0),
		];

		return $result;
	}//end update()

	/**
	 * Pair the installed widgets with the new version's, in three passes.
	 *
	 * @param array<int, WidgetPlacement> $installed The template's widgets now.
	 * @param array<int, WidgetPlacement> $wanted    The new version's widgets, unsaved.
	 *
	 * @return array{unchanged:array<int,WidgetPlacement>, added:array<int,WidgetPlacement>,
	 *               changed:array<int,array{installed:WidgetPlacement, wanted:WidgetPlacement}>,
	 *               removed:array<int,WidgetPlacement>}
	 */
	private function pair(array $installed, array $wanted): array {
		$exact = $this->pairBy(
			installed: $installed,
			wanted: $wanted,
			key: fn (WidgetPlacement $p): string => (string)json_encode(value: $this->snapshot(placement: $p)),
			uniqueOnly: false
		);
		$named = $this->pairBy(
			installed: $exact['installed'],
			wanted: $exact['wanted'],
			key: fn (WidgetPlacement $p): string => (string)json_encode(
				value: [
					$p->getWidgetId(),
					($p->getContentArray()['widgetId'] ?? null),
					$p->getCustomTitle(),
				]
			),
			uniqueOnly: false
		);
		$typed = $this->pairBy(
			installed: $named['installed'],
			wanted: $named['wanted'],
			key: static fn (WidgetPlacement $p): string => (string)$p->getWidgetId(),
			uniqueOnly: true
		);

		$unchanged = [];
		foreach ($exact['pairs'] as $pair) {
			$unchanged[] = $pair['installed'];
		}

		return [
			'unchanged' => $unchanged,
			'changed' => array_merge($named['pairs'], $typed['pairs']),
			'added' => $typed['wanted'],
			'removed' => $typed['installed'],
		];
	}//end pair()

	/**
	 * One pairing pass: widgets with the same key are paired in order.
	 *
	 * @param array<int, WidgetPlacement> $installed  Installed widgets still unpaired.
	 * @param array<int, WidgetPlacement> $wanted     New widgets still unpaired.
	 * @param callable                    $key        Gives the key two widgets must share.
	 * @param bool                        $uniqueOnly Pair a key only when one widget on each side has it.
	 *
	 * @return array{pairs:array<int,array{installed:WidgetPlacement, wanted:WidgetPlacement}>,
	 *               installed:array<int,WidgetPlacement>, wanted:array<int,WidgetPlacement>}
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
	 *      Private, and the third pass is the only one that sets it.
	 */
	private function pairBy(array $installed, array $wanted, callable $key, bool $uniqueOnly): array {
		$wantedByKey = [];
		foreach ($wanted as $index => $placement) {
			$wantedByKey[$key($placement)][] = $index;
		}

		$installedCount = array_count_values(array: array_map(callback: $key, array: $installed));

		$pairs = [];
		$leftInstalled = [];
		foreach ($installed as $placement) {
			$itsKey = $key($placement);
			$candidates = ($wantedByKey[$itsKey] ?? []);
			$ambiguous = $uniqueOnly === true && ($installedCount[$itsKey] !== 1 || count($candidates) !== 1);
			if ($candidates === [] || $ambiguous === true) {
				$leftInstalled[] = $placement;
				continue;
			}

			$index = array_shift($wantedByKey[$itsKey]);
			$pairs[] = ['installed' => $placement, 'wanted' => $wanted[$index]];
			unset($wanted[$index]);
		}

		return ['pairs' => $pairs, 'installed' => $leftInstalled, 'wanted' => array_values(array: $wanted)];
	}//end pairBy()

	/**
	 * The fields a definition sets on a widget, in a form two widgets can be
	 * compared by. JSON blobs are decoded and their keys sorted, so the same
	 * settings written in another key order are the same settings.
	 *
	 * @param WidgetPlacement $placement The widget.
	 *
	 * @return array<string, mixed> Field name to comparable value.
	 */
	private function snapshot(WidgetPlacement $placement): array {
		$snapshot = [];
		foreach (array_keys(array: self::FIELD_WORDS) as $field) {
			if ($field === 'styleConfig' || $field === 'content') {
				continue;
			}

			$snapshot[$field] = $placement->{'get' . ucfirst(string: $field)}();
		}

		$snapshot['styleConfig'] = self::sorted(value: $placement->getStyleConfigArray());
		$snapshot['content'] = self::sorted(value: $placement->getContentArray());

		return $snapshot;
	}//end snapshot()

	/**
	 * Sort an array's string keys, at every depth. Lists keep their order.
	 *
	 * @param array<int|string, mixed> $value The decoded JSON value.
	 *
	 * @return array<int|string, mixed> The same value with sorted keys.
	 */
	private static function sorted(array $value): array {
		foreach ($value as $key => $item) {
			if (is_array($item) === true) {
				$value[$key] = self::sorted(value: $item);
			}
		}

		if (array_is_list(array: $value) === false) {
			ksort($value);
		}

		return $value;
	}//end sorted()

	/**
	 * Turn a plan into the lists the administrator reads.
	 *
	 * @param array<string, mixed> $plan From {@see self::pair()}.
	 *
	 * @return array{added:array<int,string>, removed:array<int,string>,
	 *               changed:array<int,array{widget:string, fields:array<int,string>}>, unchanged:int}
	 */
	private function describe(array $plan): array {
		$changed = [];
		foreach ($plan['changed'] as $pair) {
			$before = $this->snapshot(placement: $pair['installed']);
			$after = $this->snapshot(placement: $pair['wanted']);
			$words = [];
			foreach ($after as $field => $value) {
				if ($before[$field] !== $value) {
					$words[self::FIELD_WORDS[$field]] = true;
				}
			}

			$changed[] = ['widget' => $this->label(placement: $pair['wanted']), 'fields' => array_keys(array: $words)];
		}

		return [
			'added' => array_map(callback: $this->label(...), array: $plan['added']),
			'removed' => array_map(callback: $this->label(...), array: $plan['removed']),
			'changed' => $changed,
			'unchanged' => count($plan['unchanged']),
		];
	}//end describe()

	/**
	 * How the report names a widget: its title when it has one, and its type.
	 *
	 * @param WidgetPlacement $placement The widget.
	 *
	 * @return string For example `Mijn zaken (object-list)`.
	 */
	private function label(WidgetPlacement $placement): string {
		$type = (string)$placement->getWidgetId();
		$title = (string)($placement->getCustomTitle() ?? '');
		if ($title === '') {
			$title = (string)($placement->getContentArray()['title'] ?? '');
		}

		if ($title === '') {
			return $type;
		}

		return $title . ' (' . $type . ')';
	}//end label()

	/**
	 * Write the plan to the template, in one transaction.
	 *
	 * @param Dashboard            $template The installed template.
	 * @param array<string, mixed> $plan     From {@see self::pair()}.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When a write fails. Nothing is kept then.
	 */
	private function apply(Dashboard $template, array $plan): void {
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$this->db->beginTransaction();

		try {
			foreach ($plan['changed'] as $pair) {
				$this->copyFields(from: $pair['wanted'], onto: $pair['installed'], now: $now);
				$this->placementMapper->update(entity: $pair['installed']);
			}

			foreach ($plan['added'] as $placement) {
				$this->placementMapper->insert(entity: $placement);
			}

			foreach ($plan['removed'] as $placement) {
				$this->placementMapper->delete(entity: $placement);
			}

			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$template->setUpdatedAt($now);
			$this->dashboardMapper->update(entity: $template);
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw new RuntimeException(message: 'Template not updated: ' . $e->getMessage(), previous: $e);
		}//end try
	}//end apply()

	/**
	 * Give an installed widget the new version's fields. Its row id stays,
	 * which is what a member's copy points at.
	 *
	 * @param WidgetPlacement $from The new version's widget.
	 * @param WidgetPlacement $onto The installed widget (changed in place).
	 * @param string          $now  The timestamp to stamp.
	 *
	 * @return void
	 */
	private function copyFields(WidgetPlacement $from, WidgetPlacement $onto, string $now): void {
		// Entity setters resolve through __call, which reads $args[0]; named
		// arguments would break that forwarding.
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		foreach (array_keys(array: self::FIELD_WORDS) as $field) {
			$onto->{'set' . ucfirst(string: $field)}($from->{'get' . ucfirst(string: $field)}());
		}

		$onto->setUpdatedAt($now);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
	}//end copyFields()
}//end class
