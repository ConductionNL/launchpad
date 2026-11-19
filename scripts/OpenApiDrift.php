<?php
/**
 * OpenApiDrift
 *
 * The regeneration half of the `composer lint:openapi` drift check
 * (governance-rest-api-reference D3): the committed openapi.json must be
 * exactly what `composer openapi:generate` writes, so a changed controller,
 * docblock or route that was not regenerated fails `check:strict`.
 *
 * @category  Tooling
 * @package   OCA\LaunchPad\Scripts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/rest-api-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Scripts;

/**
 * Compares the committed document with a fresh one.
 *
 * @spec openspec/specs/rest-api-reference/spec.md
 */
final class OpenApiDrift {

	/**
	 * At most this many differing operations are named; the rest are counted.
	 *
	 * @var int
	 */
	private const NAMED = 10;

	/**
	 * The problems, one line each; empty when the committed file is current.
	 *
	 * @param string $committed The committed openapi.json.
	 * @param string $generated What the generator writes now.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/specs/rest-api-reference/spec.md
	 */
	public static function problems(string $committed, string $generated): array {
		if ($committed === $generated) {
			return [];
		}

		$problems = ['openapi.json is not what `composer openapi:generate` writes; regenerate it and commit the result'];
		$changed  = self::changedParts(
			committed: (array) json_decode($committed, true),
			generated: (array) json_decode($generated, true)
		);
		foreach (array_slice($changed, 0, self::NAMED) as $part) {
			$problems[] = '  changed: '.$part;
		}

		if (count($changed) > self::NAMED) {
			$problems[] = sprintf('  ... and %d more', (count($changed) - self::NAMED));
		}

		return $problems;
	}//end problems()

	/**
	 * The operations ("VERB /path") and top-level sections that differ.
	 *
	 * @param array<string, mixed> $committed The committed document.
	 * @param array<string, mixed> $generated The fresh document.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/specs/rest-api-reference/spec.md
	 */
	public static function changedParts(array $committed, array $generated): array {
		$changed = [];
		foreach (array_unique(array_merge(array_keys($committed), array_keys($generated))) as $section) {
			if ($section !== 'paths' && ($committed[$section] ?? null) !== ($generated[$section] ?? null)) {
				$changed[] = (string) $section;
			}
		}

		$before = self::operations(document: $committed);
		$after  = self::operations(document: $generated);
		foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
			if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
				$changed[] = $key;
			}
		}

		return $changed;
	}//end changedParts()

	/**
	 * Every operation of a document by "VERB /path".
	 *
	 * @param array<string, mixed> $document The document.
	 *
	 * @return array<string, mixed>
	 */
	private static function operations(array $document): array {
		$operations = [];
		foreach ((array) ($document['paths'] ?? []) as $path => $item) {
			foreach ((array) $item as $verb => $operation) {
				$operations[strtoupper((string) $verb).' '.$path] = $operation;
			}
		}

		return $operations;
	}//end operations()
}//end class
