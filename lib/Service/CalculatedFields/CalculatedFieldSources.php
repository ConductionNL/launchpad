<?php

/**
 * CalculatedFieldSources
 *
 * Which register and schema a widget reads, and whether a server-mode
 * calculated field reads only that source (REQ-CALC-007): a field that is
 * materialised on a schema can only see that schema's objects.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\CalculatedFields
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-007-cross-widget-downgrade-on-source-mismatch
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

/**
 * Source keys and the cross-source check of server-mode fields.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-007-cross-widget-downgrade-on-source-mismatch
 */
class CalculatedFieldSources {

	/**
	 * The `register/schema` key of a placement's content: an object-list names
	 * its source at the top of its content, a stat widget under `source`.
	 *
	 * @param mixed $content The placement content.
	 *
	 * @return string `register/schema`, or '' when either is missing.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-007-cross-widget-downgrade-on-source-mismatch
	 */
	public function sourceKey(mixed $content): string {
		if (is_array($content) === false) {
			return '';
		}

		$from = $content;
		if (empty($content['register']) === true && is_array($content['source'] ?? null) === true) {
			$from = $content['source'];
		}

		$register = $from['register'] ?? null;
		$schema   = $from['schema'] ?? null;
		if (is_scalar($register) === false || is_scalar($schema) === false || (string) $register === '' || (string) $schema === '') {
			return '';
		}

		return $register.'/'.$schema;
	}//end sourceKey()

	/**
	 * Why a server-mode field cannot stay on the server, or null when it can.
	 *
	 * @param string               $id      The widget id.
	 * @param string               $own     The widget's own source key.
	 * @param array<string,mixed>  $ast     The field's AST.
	 * @param array<string,string> $sources Each widget's source key.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-007-cross-widget-downgrade-on-source-mismatch
	 */
	public function downgradeReason(string $id, string $own, array $ast, array $sources): ?string {
		if ($own === '') {
			return 'The widget has no OpenRegister source';
		}

		foreach (self::referencedWidgets(node: $ast) as $widget) {
			if ($widget !== $id && (string) ($sources[$widget] ?? '') !== $own) {
				return 'Cross-register reference';
			}
		}

		return null;
	}//end downgradeReason()

	/**
	 * The widget ids an AST reads through `widget:<id>` field references.
	 *
	 * @param mixed $node The node.
	 *
	 * @return list<string>
	 */
	private static function referencedWidgets(mixed $node): array {
		if (is_array($node) === false) {
			return [];
		}

		$found = [];
		if (($node['type'] ?? null) === 'fieldRef' && is_string($node['source'] ?? null) === true
			&& str_starts_with($node['source'], 'widget:') === true
		) {
			$found[] = substr($node['source'], strlen('widget:'));
		}

		foreach ($node as $key => $child) {
			if ($key !== 'type' && is_array($child) === true) {
				$found = array_merge($found, self::referencedWidgets(node: $child));
			}
		}

		return array_values(array_unique($found));
	}//end referencedWidgets()
}//end class
