<?php

/**
 * CalculatedFieldTypeChecker
 *
 * Server-side type inference over a calculated-field AST (REQ-CALC-002),
 * rule for rule as `src/utils/calculatedFields/typeChecker.js`: the same
 * function signatures, operator rules and error envelopes (`typeError`,
 * `functionSignature`, `unknownFunction`), so an expression the editor
 * accepts is accepted on save and one it refuses is refused with the same
 * error. A type the checker cannot know (a field without a schema) is `any`,
 * which fits everywhere; `null` fits everywhere too and unifies away.
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

/**
 * Infers the type of a calculated-field AST, refusing mismatches.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */
class CalculatedFieldTypeChecker {

	private const NUM         = ['number'];
	private const STR         = ['string'];
	private const DATE        = ['date'];
	private const LIST        = ['list'];
	private const ANY         = ['*'];
	private const NUM_OR_LIST = ['number', 'list'];

	/**
	 * Each function's parameter types, rest type and return type; `unify`
	 * names the functions whose type is that of their arguments.
	 *
	 * @var array<string,array{params: list<list<string>>, rest?: list<string>, returns?: string, unify?: string}>
	 */
	private const SIGNATURES = [
		'abs'             => ['params' => [self::NUM], 'returns' => 'number'],
		'round'           => ['params' => [self::NUM, self::NUM], 'returns' => 'number'],
		'ceil'            => ['params' => [self::NUM], 'returns' => 'number'],
		'floor'           => ['params' => [self::NUM], 'returns' => 'number'],
		'min'             => ['params' => [], 'rest' => self::NUM_OR_LIST, 'returns' => 'number'],
		'max'             => ['params' => [], 'rest' => self::NUM_OR_LIST, 'returns' => 'number'],
		'pow'             => ['params' => [self::NUM, self::NUM], 'returns' => 'number'],
		'sqrt'            => ['params' => [self::NUM], 'returns' => 'number'],
		'log'             => ['params' => [self::NUM, self::NUM], 'returns' => 'number'],
		'sum'             => ['params' => [], 'rest' => self::NUM_OR_LIST, 'returns' => 'number'],
		'avg'             => ['params' => [], 'rest' => self::NUM_OR_LIST, 'returns' => 'number'],
		'count'           => ['params' => [self::LIST], 'returns' => 'number'],
		'countIf'         => ['params' => [self::LIST, self::ANY], 'returns' => 'number'],
		'sumIf'           => ['params' => [self::LIST, self::LIST, self::ANY], 'returns' => 'number'],
		'avgIf'           => ['params' => [self::LIST, self::LIST, self::ANY], 'returns' => 'number'],
		'now'             => ['params' => [], 'returns' => 'date'],
		'today'           => ['params' => [], 'returns' => 'date'],
		'dateDiff'        => ['params' => [self::DATE, self::DATE, self::STR], 'returns' => 'number'],
		'dateAdd'         => ['params' => [self::DATE, self::NUM, self::STR], 'returns' => 'date'],
		'formatDate'      => ['params' => [self::DATE, self::STR], 'returns' => 'string'],
		'parseDate'       => ['params' => [self::STR, self::STR], 'returns' => 'date'],
		'durationBetween' => ['params' => [self::DATE, self::DATE], 'returns' => 'duration'],
		'durationFormat'  => ['params' => [['duration', 'number'], self::STR], 'returns' => 'string'],
		'concat'          => ['params' => [], 'rest' => self::ANY, 'returns' => 'string'],
		'upper'           => ['params' => [self::STR], 'returns' => 'string'],
		'lower'           => ['params' => [self::STR], 'returns' => 'string'],
		'trim'            => ['params' => [self::STR], 'returns' => 'string'],
		'contains'        => ['params' => [self::STR, self::STR], 'returns' => 'boolean'],
		'startsWith'      => ['params' => [self::STR, self::STR], 'returns' => 'boolean'],
		'endsWith'        => ['params' => [self::STR, self::STR], 'returns' => 'boolean'],
		'replace'         => ['params' => [self::STR, self::STR, self::STR], 'returns' => 'string'],
		'format'          => ['params' => [self::STR], 'rest' => self::ANY, 'returns' => 'string'],
		'coalesce'        => ['params' => [], 'rest' => self::ANY, 'unify' => 'all'],
		'ifNull'          => ['params' => [self::ANY, self::ANY], 'unify' => 'all'],
		'case'            => ['params' => [], 'rest' => self::ANY, 'unify' => 'case'],
		'toNumber'        => ['params' => [self::ANY], 'returns' => 'number'],
		'toString'        => ['params' => [self::ANY], 'returns' => 'string'],
		'toBool'          => ['params' => [self::ANY], 'returns' => 'boolean'],
		'toDate'          => ['params' => [self::ANY], 'returns' => 'date'],
	];

	private const ARITHMETIC = ['+', '-', '*', '/', '%'];
	private const ORDERING   = ['<', '<=', '>', '>='];
	private const ORDERABLE  = ['number', 'string', 'date', 'duration'];

	/**
	 * JSON-schema property types and the expression types they hold.
	 *
	 * @var array<string,string>
	 */
	private const PROPERTY_TYPES = [
		'number'   => 'number',
		'integer'  => 'number',
		'boolean'  => 'boolean',
		'array'    => 'list',
		'date'     => 'date',
		'duration' => 'duration',
	];

	/**
	 * String formats that hold a date or a duration.
	 *
	 * @var array<string,string>
	 */
	private const STRING_FORMAT_TYPES = [
		'date'      => 'date',
		'date-time' => 'date',
		'duration'  => 'duration',
	];

	/**
	 * The source row's schema, or null when unknown.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $fieldSchema = null;

	/**
	 * The return types of the calculated fields typed so far, by name.
	 *
	 * @var array<string,string>
	 */
	private array $calculatedTypes = [];

	/**
	 * The schemas of other widgets, by widget id.
	 *
	 * @var array<string,mixed>
	 */
	private array $widgetSchemas = [];

	/**
	 * The type of an expression.
	 *
	 * @param array<string,mixed>      $ast             A well-formed AST (see CalculatedFieldAstChecker).
	 * @param array<string,mixed>|null $fieldSchema     The source row's schema, or null.
	 * @param array<string,string>     $calculatedTypes The types of the widget's calculated fields so far.
	 * @param array<string,mixed>      $widgetSchemas   Other widgets' schemas, by id.
	 *
	 * @return string One of number, string, boolean, date, duration, list, null or any.
	 *
	 * @throws CalculatedFieldValidationError `typeError`, `functionSignature` or `unknownFunction`.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public function inferType(array $ast, ?array $fieldSchema=null, array $calculatedTypes=[], array $widgetSchemas=[]): string {
		$this->fieldSchema     = $fieldSchema;
		$this->calculatedTypes = $calculatedTypes;
		$this->widgetSchemas   = $widgetSchemas;

		return $this->infer(node: $ast);
	}//end inferType()

	/**
	 * The expression type a schema property holds.
	 *
	 * @param mixed $property The property (a schema, or a bare type name).
	 *
	 * @return string The type, `any` when unknown.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public static function typeOfProperty(mixed $property): string {
		$spec = $property;
		if (is_string($property) === true) {
			$spec = ['type' => $property];
		}

		if (is_array($spec) === false) {
			$spec = [];
		}

		$type = $spec['type'] ?? null;
		if (is_array($type) === true) {
			$type = array_values(array_filter($type, static fn ($t): bool => $t !== 'null'))[0] ?? null;
		}

		if ($type === 'string') {
			$format = $spec['format'] ?? null;
			if (is_string($format) === true && array_key_exists($format, self::STRING_FORMAT_TYPES) === true) {
				return self::STRING_FORMAT_TYPES[$format];
			}

			return 'string';
		}

		if (is_string($type) === true && array_key_exists($type, self::PROPERTY_TYPES) === true) {
			return self::PROPERTY_TYPES[$type];
		}

		return 'any';
	}//end typeOfProperty()

	/**
	 * The type of a dotted path in a schema.
	 *
	 * @param mixed  $schema The schema: JSON schema with `properties`, or a flat name => type map.
	 * @param string $path   The path.
	 *
	 * @return string The type, `any` when unknown.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public static function typeOfPath(mixed $schema, string $path): string {
		if (is_array($schema) === false) {
			return 'any';
		}

		if (isset($schema['properties']) === false && array_key_exists($path, $schema) === true) {
			return self::typeOfProperty(property: $schema[$path]);
		}

		$node = $schema;
		foreach (explode('.', $path) as $part) {
			$next = $node['properties'][$part] ?? null;
			if ($next === null && ($node['type'] ?? null) === 'array') {
				$next = $node['items']['properties'][$part] ?? null;
			}

			if (is_array($next) === false) {
				return 'any';
			}

			$node = $next;
		}

		return self::typeOfProperty(property: $node);
	}//end typeOfPath()

	/**
	 * The type of one node.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return string
	 */
	private function infer(array $node): string {
		return match ($node['type'] ?? null) {
			'literal'       => (string) $node['valueType'],
			'fieldRef'      => $this->fieldRefType(node: $node),
			'calculatedRef' => $this->calculatedTypes[$node['name']] ?? 'any',
			'unary'         => $this->unaryType(node: $node),
			'binary'        => $this->binaryType(node: $node),
			'if'            => $this->ifType(node: $node),
			'call'          => $this->callType(node: $node),
			default         => throw new CalculatedFieldValidationError(
				detail: ['error' => 'typeError', 'message' => "Unknown node '".(string) ($node['type'] ?? '')."'"]
			),
		};
	}//end infer()

	/**
	 * The type of a field reference, from its own or the other widget's schema.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return string
	 */
	private function fieldRefType(array $node): string {
		if ($node['source'] === 'self') {
			return self::typeOfPath(schema: $this->fieldSchema, path: $node['path']);
		}

		$widget = substr($node['source'], strlen('widget:'));
		return self::typeOfPath(schema: $this->widgetSchemas[$widget] ?? null, path: $node['path']);
	}//end fieldRefType()

	/**
	 * Whether a type fits a parameter's allowed types.
	 *
	 * @param string       $actual  The type.
	 * @param list<string> $allowed The allowed types; `*` allows any.
	 *
	 * @return bool
	 */
	private static function fits(string $actual, array $allowed): bool {
		return $actual === 'any' || $actual === 'null' || in_array('*', $allowed, true) === true
			|| in_array($actual, $allowed, true) === true;
	}//end fits()

	/**
	 * The one type a set of branches or arguments shares.
	 *
	 * @param list<string>                    $types The types.
	 * @param list<array<string,mixed>>       $nodes The nodes they belong to.
	 *
	 * @return string
	 *
	 * @throws CalculatedFieldValidationError `typeError` when two known types differ.
	 */
	private static function unify(array $types, array $nodes): string {
		$known = [];
		$names = [];
		foreach ($types as $i => $type) {
			if ($type !== 'null') {
				$known[] = $type;
				$names[] = self::describe(node: $nodes[$i]);
			}
		}

		if ($known === []) {
			return 'null';
		}

		if (in_array('any', $known, true) === true) {
			return 'any';
		}

		$first = $known[0];
		foreach ($known as $i => $type) {
			if ($type !== $first) {
				throw new CalculatedFieldValidationError(
					detail: [
						'error'    => 'typeError',
						'message'  => 'The values '.$names[0].' ('.$first.') and '.$names[$i].' ('.$type.') do not have the same type',
						'operands' => [$names[0], $names[$i]],
					]
				);
			}
		}

		return $first;
	}//end unify()

	/**
	 * The `functionSignature` error of one argument.
	 *
	 * @param string              $name    The function.
	 * @param int                 $index   The argument's index.
	 * @param array<string,mixed> $node    The argument.
	 * @param string              $actual  Its type.
	 * @param list<string>        $allowed The allowed types.
	 *
	 * @return CalculatedFieldValidationError
	 */
	private static function signatureError(string $name, int $index, array $node, string $actual, array $allowed): CalculatedFieldValidationError {
		return new CalculatedFieldValidationError(
			detail: [
				'error'    => 'functionSignature',
				'name'     => $name,
				'argument' => ($index + 1),
				'message'  => "'".$name."' argument ".($index + 1).' must be '.implode(' or ', $allowed)
					.', got '.self::describe(node: $node).' ('.$actual.')',
			]
		);
	}//end signatureError()

	/**
	 * The type of a binary expression.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return string
	 *
	 * @throws CalculatedFieldValidationError `typeError` when the operator does not apply.
	 */
	private function binaryType(array $node): string {
		$left   = $this->infer(node: $node['left']);
		$right  = $this->infer(node: $node['right']);
		$known  = array_values(array_filter([$left, $right], static fn (string $type): bool => $type !== 'any' && $type !== 'null'));
		$result = self::operatorResult(op: $node['op'], known: $known);
		if ($result !== null) {
			return $result;
		}

		$leftName  = self::describe(node: $node['left']);
		$rightName = self::describe(node: $node['right']);
		throw new CalculatedFieldValidationError(
			detail: [
				'error'    => 'typeError',
				'message'  => "Cannot apply '".$node['op']."' to ".$leftName.' ('.$left.') and '.$rightName.' ('.$right.')',
				'operands' => [$leftName, $rightName],
			]
		);
	}//end binaryType()

	/**
	 * The result type of an operator over its known operand types, or null when it does not apply.
	 *
	 * @param string       $op    The operator.
	 * @param list<string> $known The operand types other than any and null.
	 *
	 * @return string|null
	 */
	private static function operatorResult(string $op, array $known): ?string {
		$want = null;
		if (in_array($op, self::ARITHMETIC, true) === true) {
			$want = 'number';
		}

		if ($op === 'and' || $op === 'or') {
			$want = 'boolean';
		}

		if ($want !== null) {
			if (array_filter($known, static fn (string $type): bool => $type !== $want) === []) {
				return $want;
			}

			return null;
		}

		return self::comparisonResult(op: $op, known: $known);
	}//end operatorResult()

	/**
	 * The result type of a comparison: boolean over two equal types, ordering only over orderable ones.
	 *
	 * @param string       $op    The operator.
	 * @param list<string> $known The operand types other than any and null.
	 *
	 * @return string|null
	 */
	private static function comparisonResult(string $op, array $known): ?string {
		if (in_array($op, self::ORDERING, true) === true
			&& array_filter($known, static fn (string $type): bool => in_array($type, self::ORDERABLE, true) === false) !== []
		) {
			return null;
		}

		if (count($known) === 2 && $known[0] !== $known[1]) {
			return null;
		}

		return 'boolean';
	}//end comparisonResult()

	/**
	 * The type of a unary expression.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return string
	 *
	 * @throws CalculatedFieldValidationError `typeError` when the operator does not apply.
	 */
	private function unaryType(array $node): string {
		$type    = $this->infer(node: $node['operand']);
		$allowed = ['number', 'duration'];
		if ($node['op'] === 'not') {
			$allowed = ['boolean'];
		}

		if (self::fits(actual: $type, allowed: $allowed) === false) {
			$name = self::describe(node: $node['operand']);
			throw new CalculatedFieldValidationError(
				detail: [
					'error'    => 'typeError',
					'message'  => "Cannot apply '".$node['op']."' to ".$name.' ('.$type.')',
					'operands' => [$name],
				]
			);
		}

		if ($node['op'] === 'not') {
			return 'boolean';
		}

		if ($type === 'duration') {
			return 'duration';
		}

		return 'number';
	}//end unaryType()

	/**
	 * The type of a conditional: its condition is boolean, its branches share a type.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return string
	 *
	 * @throws CalculatedFieldValidationError `typeError`.
	 */
	private function ifType(array $node): string {
		$cond = $this->infer(node: $node['cond']);
		if (self::fits(actual: $cond, allowed: ['boolean']) === false) {
			$name = self::describe(node: $node['cond']);
			throw new CalculatedFieldValidationError(
				detail: [
					'error'    => 'typeError',
					'message'  => 'The condition '.$name.' must be boolean, got '.$cond,
					'operands' => [$name],
				]
			);
		}

		return self::unify(
			types: [$this->infer(node: $node['then']), $this->infer(node: $node['else'])],
			nodes: [$node['then'], $node['else']]
		);
	}//end ifType()

	/**
	 * The type of a function call.
	 *
	 * @param array<string,mixed> $node The node.
	 *
	 * @return string
	 *
	 * @throws CalculatedFieldValidationError `unknownFunction`, `functionSignature` or `typeError`.
	 */
	private function callType(array $node): string {
		$name = $node['name'];
		if (array_key_exists($name, self::SIGNATURES) === false) {
			throw new CalculatedFieldValidationError(
				detail: ['error' => 'unknownFunction', 'name' => $name, 'message' => "Unknown function '".$name."'"]
			);
		}

		$signature = self::SIGNATURES[$name];
		$types     = array_map(fn (array $arg): string => $this->infer(node: $arg), $node['args']);
		foreach ($types as $i => $type) {
			$allowed = $signature['params'][$i] ?? ($signature['rest'] ?? null);
			if ($allowed !== null && self::fits(actual: $type, allowed: $allowed) === false) {
				throw self::signatureError(name: $name, index: $i, node: $node['args'][$i], actual: $type, allowed: $allowed);
			}
		}

		if (isset($signature['unify']) === false) {
			return $signature['returns'];
		}

		if ($signature['unify'] === 'case') {
			return self::caseType(types: $types, args: $node['args']);
		}

		return self::unify(types: $types, nodes: $node['args']);
	}//end callType()

	/**
	 * The type of `case(cond1, value1, ..., default?)`: every condition boolean, the values unified.
	 *
	 * @param list<string>              $types The argument types.
	 * @param list<array<string,mixed>> $args  The arguments.
	 *
	 * @return string
	 *
	 * @throws CalculatedFieldValidationError `functionSignature` or `typeError`.
	 */
	private static function caseType(array $types, array $args): string {
		$values = [];
		$nodes  = [];
		$total  = count($types);
		for ($i = 0; $i < $total; $i += 2) {
			if (($i + 1) >= $total) {
				$values[] = $types[$i];
				$nodes[]  = $args[$i];
				break;
			}

			if (self::fits(actual: $types[$i], allowed: ['boolean']) === false) {
				throw self::signatureError(name: 'case', index: $i, node: $args[$i], actual: $types[$i], allowed: ['boolean']);
			}

			$values[] = $types[($i + 1)];
			$nodes[]  = $args[($i + 1)];
		}

		return self::unify(types: $values, nodes: $nodes);
	}//end caseType()

	/**
	 * Describe one node.
	 *
	 * @param mixed $node The node.
	 *
	 * @return string The description, `?` for an unknown node.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public static function describe(mixed $node): string {
		if (is_array($node) === false) {
			return '?';
		}

		return match ($node['type'] ?? null) {
			'fieldRef'      => self::describeFieldRef(node: $node),
			'calculatedRef' => (string) $node['name'],
			'literal'       => self::describeLiteral(node: $node),
			'call'          => $node['name'].'(…)',
			'binary'        => '(… '.$node['op'].' …)',
			'unary'         => $node['op'].' …',
			'if'            => 'if(…)',
			default         => '?',
		};
	}//end describe()

	/**
	 * Describe a field reference: the path on the own row, else `widget:<id>.<path>`.
	 *
	 * @param array<string,mixed> $node The fieldRef node.
	 *
	 * @return string
	 */
	private static function describeFieldRef(array $node): string {
		if ($node['source'] === 'self') {
			return (string) $node['path'];
		}

		return $node['source'].'.'.$node['path'];
	}//end describeFieldRef()

	/**
	 * Describe a literal as JavaScript's `String()` / `JSON.stringify()` would.
	 *
	 * @param array<string,mixed> $node The literal node.
	 *
	 * @return string
	 */
	private static function describeLiteral(array $node): string {
		$value = $node['value'];
		return match (true) {
			$node['valueType'] === 'string' => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			$value === true                 => 'true',
			$value === false                => 'false',
			$value === null                 => 'null',
			default                         => (string) $value,
		};
	}//end describeLiteral()
}//end class
