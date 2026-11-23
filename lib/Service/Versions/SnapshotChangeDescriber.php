<?php

/**
 * SnapshotChangeDescriber
 *
 * Compares two dashboard version snapshots and describes what changed
 * between them in one short line, for the "What changed" column of the
 * Versions tab (REQ-VERS-001). Snapshots have the shape built by
 * {@see \OCA\LaunchPad\Service\DashboardVersionService}:
 * `{"dashboard": {...}, "placements": [{...}, ...]}`.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Versions
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

namespace OCA\LaunchPad\Service\Versions;

use OCP\IL10N;

/**
 * Describes the difference between two dashboard snapshots.
 *
 * @spec openspec/specs/dashboard-versioning/spec.md
 */
class SnapshotChangeDescriber {

	/**
	 * Longest description returned, in characters.
	 *
	 * @var integer
	 */
	public const MAX_LENGTH = 120;

	/**
	 * Constructor
	 *
	 * @param IL10N|null $l10n The app's translations; null keeps the English source strings.
	 */
	public function __construct(
		private readonly ?IL10N $l10n=null,
	) {
	}//end __construct()

	/**
	 * Describe what changed from one snapshot to the next.
	 *
	 * The first change is named and the rest counted, for example
	 * "Widget Inbox added and 2 more changes".
	 *
	 * @param string|null $previousJson The older snapshot, or null when there is none.
	 * @param string      $currentJson  The newer snapshot.
	 *
	 * @return string|null One line of at most MAX_LENGTH characters, or null when
	 *                     there is no previous snapshot or nothing differs.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function describe(?string $previousJson, string $currentJson): ?string {
		if ($previousJson === null) {
			return null;
		}

		$previous = $this->decode(json: $previousJson);
		$current  = $this->decode(json: $currentJson);
		if ($previous === null || $current === null) {
			return null;
		}

		$changes = array_merge(
			$this->dashboardChanges(
				previous: $this->section(snapshot: $previous, key: 'dashboard'),
				current: $this->section(snapshot: $current, key: 'dashboard')
			),
			$this->placementChanges(
				previous: $this->placementsById(snapshot: $previous),
				current: $this->placementsById(snapshot: $current)
			)
		);

		if ($changes === []) {
			return null;
		}

		$line = $changes[0];
		$more = (count($changes) - 1);
		if ($more > 0) {
			$line = $this->plural(
				singular: '%s and %n more change',
				plural: '%s and %n more changes',
				count: $more,
				parameters: [$changes[0]]
			);
		}

		return $this->shorten(line: $line);
	}//end describe()

	/**
	 * The changes to the dashboard itself: its name and its column count.
	 *
	 * @param array<string, mixed> $previous The older dashboard fields.
	 * @param array<string, mixed> $current  The newer dashboard fields.
	 *
	 * @return list<string> The descriptions.
	 */
	private function dashboardChanges(array $previous, array $current): array {
		$changes = [];
		$oldName = $this->text(value: $previous['name'] ?? null);
		$newName = $this->text(value: $current['name'] ?? null);
		if ($oldName !== $newName && $newName !== '') {
			$changes[] = $this->translate(text: 'Dashboard renamed to %s', parameters: [$newName]);
		}

		$oldColumns = $this->text(value: $previous['gridColumns'] ?? null);
		$newColumns = $this->text(value: $current['gridColumns'] ?? null);
		if ($oldColumns !== $newColumns && $oldColumns !== '' && $newColumns !== '') {
			$changes[] = $this->translate(text: 'Columns from %1$s to %2$s', parameters: [$oldColumns, $newColumns]);
		}

		return $changes;
	}//end dashboardChanges()

	/**
	 * The added, removed, renamed, moved and resized widgets, matched by placement id.
	 *
	 * @param array<string, array<string, mixed>> $previous The older placements by id.
	 * @param array<string, array<string, mixed>> $current  The newer placements by id.
	 *
	 * @return list<string> The descriptions.
	 */
	private function placementChanges(array $previous, array $current): array {
		$changes = [];
		foreach ($current as $id => $placement) {
			if (isset($previous[$id]) === false) {
				$changes[] = $this->translate(text: 'Widget %s added', parameters: [$this->widgetName(placement: $placement)]);
			}
		}

		foreach ($previous as $id => $placement) {
			if (isset($current[$id]) === false) {
				$changes[] = $this->translate(text: 'Widget %s removed', parameters: [$this->widgetName(placement: $placement)]);
			}
		}

		foreach ($current as $id => $placement) {
			if (isset($previous[$id]) === true) {
				array_push($changes, ...$this->changedPlacement(before: $previous[$id], after: $placement));
			}
		}

		return $changes;
	}//end placementChanges()

	/**
	 * The changes to one placement present in both snapshots.
	 *
	 * @param array<string, mixed> $before The placement in the older snapshot.
	 * @param array<string, mixed> $after  The placement in the newer snapshot.
	 *
	 * @return list<string> The descriptions.
	 */
	private function changedPlacement(array $before, array $after): array {
		$changes = [];
		$oldName = $this->widgetName(placement: $before);
		$newName = $this->widgetName(placement: $after);
		if ($oldName !== $newName) {
			$changes[] = $this->translate(text: 'Widget %1$s renamed to %2$s', parameters: [$oldName, $newName]);
		}

		if ($this->differs(before: $before, after: $after, fields: ['gridX', 'gridY']) === true) {
			$changes[] = $this->translate(text: 'Widget %s moved', parameters: [$newName]);
		}

		if ($this->differs(before: $before, after: $after, fields: ['gridWidth', 'gridHeight']) === true) {
			$changes[] = $this->translate(text: 'Widget %s resized', parameters: [$newName]);
		}

		return $changes;
	}//end changedPlacement()

	/**
	 * Whether any of the given fields holds a different value.
	 *
	 * @param array<string, mixed> $before The older placement.
	 * @param array<string, mixed> $after  The newer placement.
	 * @param list<string>         $fields The fields to compare.
	 *
	 * @return boolean True when at least one field differs.
	 */
	private function differs(array $before, array $after, array $fields): bool {
		foreach ($fields as $field) {
			if ($this->text(value: $before[$field] ?? null) !== $this->text(value: $after[$field] ?? null)) {
				return true;
			}
		}

		return false;
	}//end differs()

	/**
	 * The name a placement shows: its custom title, else its content title, else its widget id.
	 *
	 * @param array<string, mixed> $placement The placement.
	 *
	 * @return string The name.
	 */
	private function widgetName(array $placement): string {
		$customTitle = $this->text(value: $placement['customTitle'] ?? null);
		if ($customTitle !== '') {
			return $customTitle;
		}

		$content = $placement['content'] ?? null;
		if (is_array($content) === true) {
			$contentTitle = $this->text(value: $content['title'] ?? null);
			if ($contentTitle !== '') {
				return $contentTitle;
			}
		}

		return $this->text(value: $placement['widgetId'] ?? null);
	}//end widgetName()

	/**
	 * The placements of a snapshot keyed by their id; placements without one are skipped.
	 *
	 * @param array<string, mixed> $snapshot The decoded snapshot.
	 *
	 * @return array<string, array<string, mixed>> The placements by id.
	 */
	private function placementsById(array $snapshot): array {
		$byId = [];
		$placements = $snapshot['placements'] ?? [];
		if (is_array($placements) === false) {
			return $byId;
		}

		foreach ($placements as $placement) {
			if (is_array($placement) === false) {
				continue;
			}

			$id = $this->text(value: $placement['uuid'] ?? $placement['id'] ?? null);
			if ($id !== '') {
				$byId[$id] = $placement;
			}
		}

		return $byId;
	}//end placementsById()

	/**
	 * One object-valued section of a snapshot, or an empty array.
	 *
	 * @param array<string, mixed> $snapshot The decoded snapshot.
	 * @param string               $key      The section key.
	 *
	 * @return array<string, mixed> The section.
	 */
	private function section(array $snapshot, string $key): array {
		$section = $snapshot[$key] ?? [];
		if (is_array($section) === false) {
			return [];
		}

		return $section;
	}//end section()

	/**
	 * Decode a snapshot, or null when it is not a JSON object.
	 *
	 * @param string $json The snapshot JSON.
	 *
	 * @return array<string, mixed>|null The decoded snapshot.
	 */
	private function decode(string $json): ?array {
		$decoded = json_decode($json, associative: true);
		if (is_array($decoded) === false) {
			return null;
		}

		return $decoded;
	}//end decode()

	/**
	 * A scalar as a trimmed string; anything else as the empty string.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string The text.
	 */
	private function text(mixed $value): string {
		if (is_scalar($value) === false) {
			return '';
		}

		return trim((string)$value);
	}//end text()

	/**
	 * Translate a source string with sprintf parameters.
	 *
	 * @param string       $text       The English source string.
	 * @param list<string> $parameters The values for its placeholders.
	 *
	 * @return string The translated text.
	 */
	private function translate(string $text, array $parameters): string {
		if ($this->l10n !== null) {
			return $this->l10n->t($text, $parameters);
		}

		return vsprintf($text, $parameters);
	}//end translate()

	/**
	 * Translate a counted source string, where `%n` stands for the count.
	 *
	 * @param string       $singular   The source string for a count of one.
	 * @param string       $plural     The source string for any other count.
	 * @param integer      $count      The count.
	 * @param list<string> $parameters The values for its other placeholders.
	 *
	 * @return string The translated text.
	 */
	private function plural(string $singular, string $plural, int $count, array $parameters): string {
		if ($this->l10n !== null) {
			return $this->l10n->n($singular, $plural, $count, $parameters);
		}

		$text = $plural;
		if ($count === 1) {
			$text = $singular;
		}

		return vsprintf(str_replace(search: '%n', replace: (string)$count, subject: $text), $parameters);
	}//end plural()

	/**
	 * Cut a line to MAX_LENGTH characters, ending in an ellipsis when cut.
	 *
	 * @param string $line The line.
	 *
	 * @return string The line, at most MAX_LENGTH characters.
	 */
	private function shorten(string $line): string {
		if (mb_strlen($line) <= self::MAX_LENGTH) {
			return $line;
		}

		return rtrim(mb_substr($line, 0, (self::MAX_LENGTH - 1))).'…';
	}//end shorten()
}//end class
