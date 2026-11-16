<?php

/**
 * CalculatedFieldCycleDetector
 *
 * Server-side cycle detection over the calculated fields of a set of widgets
 * (REQ-CALC-006), node for node as `src/utils/calculatedFields/cycleDetector.js`:
 * a field may not depend on itself through other calculated fields, and two
 * widgets may not read each other through cross-widget references. The
 * fields come back in topological order, dependencies first.
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

/**
 * Topological order of calculated fields, refusing cycles.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
class CalculatedFieldCycleDetector {

	/**
	 * The fields of all widgets in dependency order, as `<widgetId>.<name>`.
	 *
	 * @param array<int,array<string,mixed>> $widgets The widgets, each `{id, calculatedFields}`
	 *                                                with `{name, compiledExpression}` fields.
	 *
	 * @return array<int,string> The fields, dependencies first.
	 *
	 * @throws CalculatedFieldCycleError When the fields or the widgets form a cycle.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
	 */
	public function assertAcyclic(array $widgets): array {
		[$fieldGraph, $widgetGraph] = $this->dependencyGraphs(widgets: $widgets);

		$fields = $this->topologicalOrder(graph: $fieldGraph);
		if ($fields['cycle'] !== null) {
			$owners = array_map(
				static fn (string $node): string => substr($node, 0, (int) strpos($node, '.')),
				$fields['cycle']
			);
			throw new CalculatedFieldCycleError(cycle: $fields['cycle'], widgets: $owners);
		}

		$across = $this->topologicalOrder(graph: $widgetGraph);
		if ($across['cycle'] !== null) {
			throw new CalculatedFieldCycleError(cycle: $across['cycle'], widgets: $across['cycle']);
		}

		return $fields['order'];
	}//end assertAcyclic()

	/**
	 * The field graph (`<widget>.<field>` => its field dependencies) and the
	 * widget graph (widget => the widgets it reads).
	 *
	 * @param array<int,array<string,mixed>> $widgets The widgets.
	 *
	 * @return array{0: array<string,array<string,true>>, 1: array<string,array<string,true>>} The graphs.
	 */
	private function dependencyGraphs(array $widgets): array {
		$fieldGraph  = [];
		$widgetGraph = [];
		$calculated  = [];
		foreach ($widgets as $widget) {
			$id = (string) ($widget['id'] ?? '');
			$calculated[$id]  = [];
			$widgetGraph[$id] = [];
			foreach ($this->fieldsOf(widget: $widget) as $field) {
				$calculated[$id][(string) $field['name']] = true;
			}
		}

		foreach ($widgets as $widget) {
			$id = (string) ($widget['id'] ?? '');
			foreach ($this->fieldsOf(widget: $widget) as $field) {
				$fieldGraph[$id.'.'.$field['name']] = $this->fieldDependencies(
					id: $id,
					ast: $field['compiledExpression'],
					calculated: $calculated,
					widgetGraph: $widgetGraph
				);
			}
		}

		return [$fieldGraph, $widgetGraph];
	}//end dependencyGraphs()

	/**
	 * The calculated fields of a widget that carry a name and an AST.
	 *
	 * @param array<string,mixed> $widget The widget.
	 *
	 * @return array<int,array<string,mixed>> The fields.
	 */
	private function fieldsOf(array $widget): array {
		$fields = $widget['calculatedFields'] ?? [];
		if (is_array($fields) === false) {
			return [];
		}

		return array_values(
			array_filter(
				$fields,
				static fn ($field): bool => is_array($field) === true
					&& isset($field['name']) === true
					&& is_array($field['compiledExpression'] ?? null) === true
			)
		);
	}//end fieldsOf()

	/**
	 * The fields one field depends on; links the widgets it reads.
	 *
	 * @param string                             $id          The field's widget.
	 * @param array<string,mixed>                $ast         The field's AST.
	 * @param array<string,array<string,true>>   $calculated  Field names per widget.
	 * @param array<string,array<string,true>>   $widgetGraph The widget graph, extended in place.
	 *
	 * @return array<string,true> The dependencies.
	 */
	private function fieldDependencies(string $id, array $ast, array $calculated, array &$widgetGraph): array {
		$refs         = $this->referencesOf(node: $ast);
		$dependencies = [];
		foreach ($refs['calculated'] as $name) {
			$dependencies[$id.'.'.$name] = true;
		}

		foreach ($refs['fields'] as $ref) {
			$target = self::targetOf(id: $id, ref: $ref);
			self::linkWidgets(id: $id, target: $target, widgetGraph: $widgetGraph);
			if (isset($calculated[$target][$ref['path']]) === true) {
				$dependencies[$target.'.'.$ref['path']] = true;
			}
		}

		return $dependencies;
	}//end fieldDependencies()

	/**
	 * The widget a field reference reads: its own for `self`, else the id after `widget:`.
	 *
	 * @param string                              $id  The field's widget.
	 * @param array{source: string, path: string} $ref The reference.
	 *
	 * @return string The widget id.
	 */
	private static function targetOf(string $id, array $ref): string {
		if ($ref['source'] === 'self') {
			return $id;
		}

		return substr($ref['source'], strlen('widget:'));
	}//end targetOf()

	/**
	 * Link a widget to another widget it reads; a self-read adds no edge.
	 *
	 * @param string                           $id          The reading widget.
	 * @param string                           $target      The widget read.
	 * @param array<string,array<string,true>> $widgetGraph The widget graph, extended in place.
	 *
	 * @return void
	 */
	private static function linkWidgets(string $id, string $target, array &$widgetGraph): void {
		if ($target === $id) {
			return;
		}

		$widgetGraph[$id][$target] = true;
		$widgetGraph[$target]      = ($widgetGraph[$target] ?? []);
	}//end linkWidgets()

	/**
	 * The field and calculated-field references of an AST.
	 *
	 * @param mixed $node An AST node.
	 *
	 * @return array{fields: array<int,array{source: string, path: string}>, calculated: array<int,string>} The references.
	 */
	private function referencesOf(mixed $node): array {
		$found = ['fields' => [], 'calculated' => []];
		$this->collectReferences(node: $node, found: $found);
		return $found;
	}//end referencesOf()

	/**
	 * Collect the references of a node and its children.
	 *
	 * @param mixed                                                                                    $node  The node.
	 * @param array{fields: array<int,array{source: string, path: string}>, calculated: array<int,string>} $found The references, extended in place.
	 *
	 * @return void
	 */
	private function collectReferences(mixed $node, array &$found): void {
		if (is_array($node) === false) {
			return;
		}

		self::recordReference(node: $node, found: $found);
		foreach (self::childrenOf(node: $node) as $child) {
			$this->collectReferences(node: $child, found: $found);
		}
	}//end collectReferences()

	/**
	 * Record a node's own reference, when it is a field or calculated-field reference.
	 *
	 * @param array<string,mixed>                                                                      $node  The node.
	 * @param array{fields: array<int,array{source: string, path: string}>, calculated: array<int,string>} $found The references, extended in place.
	 *
	 * @return void
	 */
	private static function recordReference(array $node, array &$found): void {
		$type = $node['type'] ?? null;
		if ($type === 'fieldRef') {
			$found['fields'][] = self::fieldRefOf(node: $node);
		} else if ($type === 'calculatedRef') {
			$found['calculated'][] = (string) ($node['name'] ?? '');
		}
	}//end recordReference()

	/**
	 * The source and path of a field reference node.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return array{source: string, path: string} The reference.
	 */
	private static function fieldRefOf(array $node): array {
		return ['source' => (string) ($node['source'] ?? 'self'), 'path' => (string) ($node['path'] ?? '')];
	}//end fieldRefOf()

	/**
	 * The child nodes of a node: its operands, then its arguments.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return array<int,mixed> The children.
	 */
	private static function childrenOf(array $node): array {
		$children = [];
		foreach (['left', 'right', 'operand', 'cond', 'then', 'else'] as $key) {
			$children[] = ($node[$key] ?? null);
		}

		$args = $node['args'] ?? [];
		if (is_array($args) === true) {
			$children = array_merge($children, array_values($args));
		}

		return $children;
	}//end childrenOf()

	/**
	 * A depth-first topological order of a graph, or the first cycle found.
	 *
	 * @param array<string,array<string,true>> $graph The graph.
	 *
	 * @return array{order: array<int,string>, cycle: array<int,string>|null} The order, empty on a cycle.
	 */
	private function topologicalOrder(array $graph): array {
		$walk  = ['graph' => $graph, 'state' => [], 'order' => [], 'stack' => [], 'cycle' => null];
		$nodes = array_map('strval', array_keys($graph));
		sort($nodes, SORT_STRING);
		foreach ($nodes as $node) {
			$this->visit(walk: $walk, node: $node);
		}

		if ($walk['cycle'] !== null) {
			return ['order' => [], 'cycle' => $walk['cycle']];
		}

		return ['order' => $walk['order'], 'cycle' => null];
	}//end topologicalOrder()

	/**
	 * Visit a node: its dependencies first, then itself.
	 *
	 * @param array<string,mixed> $walk The walk state, updated in place.
	 * @param string              $node The node.
	 *
	 * @return void
	 */
	private function visit(array &$walk, string $node): void {
		$state = $walk['state'][$node] ?? null;
		if ($walk['cycle'] !== null || $state === 'done') {
			return;
		}

		if ($state === 'active') {
			$from          = (int) array_search($node, $walk['stack'], true);
			$walk['cycle'] = array_merge(array_slice($walk['stack'], $from), [$node]);
			return;
		}

		$walk['state'][$node] = 'active';
		$walk['stack'][]      = $node;
		$dependencies         = array_map('strval', array_keys($walk['graph'][$node] ?? []));
		sort($dependencies, SORT_STRING);
		foreach ($dependencies as $dependency) {
			$this->visit(walk: $walk, node: $dependency);
		}

		array_pop($walk['stack']);
		$walk['state'][$node] = 'done';
		$walk['order'][]      = $node;
	}//end visit()
}//end class
