<?php
/**
 * OpenApiTypeMapper
 *
 * Turns a PHPDoc type (`int`, `?string`, `list<array{id: int}>`, a
 * `@psalm-type` alias, ...) into an OpenAPI 3.1 schema, for the generator
 * behind `composer openapi:generate` (governance-rest-api-reference D1).
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
 * @spec openspec/changes/governance-rest-api-reference/design.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Scripts;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Maps PHPDoc types to OpenAPI 3.1 schemas.
 *
 * A name in `$aliases` becomes a `$ref` to `#/components/schemas/<name>`.
 * A class name, `mixed` and anything not understood become `{}` (any value),
 * so an unknown type documents less, never something wrong.
 *
 * @spec openspec/changes/governance-rest-api-reference/design.md
 */
final class OpenApiTypeMapper {

	/**
	 * The scalar PHPDoc names and their JSON Schema type.
	 *
	 * @var array<string, string>
	 */
	private const SCALARS = [
		'int'              => 'integer',
		'integer'          => 'integer',
		'positive-int'     => 'integer',
		'non-negative-int' => 'integer',
		'float'            => 'number',
		'double'           => 'number',
		'string'           => 'string',
		'non-empty-string' => 'string',
		'numeric-string'   => 'string',
		'class-string'     => 'string',
		'bool'             => 'boolean',
		'boolean'          => 'boolean',
		'true'             => 'boolean',
		'false'            => 'boolean',
		'null'             => 'null',
		'void'             => 'null',
		'object'           => 'object',
		'stdClass'         => 'object',
		'\stdClass'        => 'object',
		'array'            => 'array',
		'list'             => 'array',
		'non-empty-array'  => 'array',
		'non-empty-list'   => 'array',
		'iterable'         => 'array',
	];

	/**
	 * The parser of PHPDoc types.
	 *
	 * @var TypeParser
	 */
	private TypeParser $parser;

	/**
	 * The PHPDoc lexer.
	 *
	 * @var Lexer
	 */
	private Lexer $lexer;

	/**
	 * Build a mapper.
	 *
	 * @param array<string, true> $aliases The `@psalm-type` names that are component schemas.
	 * @param array<string, int>  $statusCodes The `Http::STATUS_*` constant names and their codes.
	 */
	public function __construct(
		private readonly array $aliases=[],
		private readonly array $statusCodes=[],
	) {
		$config       = new ParserConfig(usedAttributes: []);
		$this->lexer  = new Lexer(config: $config);
		$this->parser = new TypeParser(config: $config, constExprParser: new ConstExprParser(config: $config));
	}//end __construct()

	/**
	 * Parse a PHPDoc type string, or null when it does not parse.
	 *
	 * @param string $type The type as written.
	 *
	 * @return TypeNode|null
	 *
	 * @spec openspec/changes/governance-rest-api-reference/design.md
	 */
	public function parse(string $type): ?TypeNode {
		try {
			$tokens = new TokenIterator(tokens: $this->lexer->tokenize(s: $type));
			return $this->parser->parse(tokens: $tokens);
		} catch (\Throwable) {
			return null;
		}
	}//end parse()

	/**
	 * The schema of a type string; `{}` when it does not parse.
	 *
	 * @param string $type The type as written.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/governance-rest-api-reference/design.md
	 */
	public function schemaOf(string $type): array {
		$node = $this->parse(type: $type);
		if ($node === null) {
			return [];
		}

		return $this->schema(node: $node);
	}//end schemaOf()

	/**
	 * The schema of a parsed type.
	 *
	 * @param TypeNode $node The type.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/governance-rest-api-reference/design.md
	 */
	public function schema(TypeNode $node): array {
		return match (true) {
			$node instanceof NullableTypeNode => $this->union(members: [$node->type, new IdentifierTypeNode(name: 'null')]),
			$node instanceof UnionTypeNode => $this->union(members: $node->types),
			$node instanceof ArrayShapeNode => $this->shape(node: $node),
			$node instanceof ArrayTypeNode => ['type' => 'array', 'items' => (object) $this->schema(node: $node->type)],
			$node instanceof GenericTypeNode => $this->generic(node: $node),
			$node instanceof ConstTypeNode => $this->constant(node: $node),
			$node instanceof IdentifierTypeNode => $this->identifier(name: $node->name),
			default => [],
		};
	}//end schema()

	/**
	 * The HTTP status a type stands for (`Http::STATUS_OK`, `200`), or null.
	 *
	 * @param TypeNode $node The first argument of a `JSONResponse<...>`.
	 *
	 * @return int|null
	 *
	 * @spec openspec/changes/governance-rest-api-reference/design.md
	 */
	public function statusOf(TypeNode $node): ?int {
		if ($node instanceof ConstTypeNode === false) {
			return null;
		}

		$expr = $node->constExpr;
		if ($expr instanceof ConstExprIntegerNode) {
			return (int) $expr->value;
		}

		if ($expr instanceof ConstFetchNode) {
			return ($this->statusCodes[$expr->name] ?? null);
		}

		return null;
	}//end statusOf()

	/**
	 * A union: a nullable type list when every member is a plain type, else `anyOf`.
	 *
	 * @param array<TypeNode> $members The members.
	 *
	 * @return array<string, mixed>
	 */
	private function union(array $members): array {
		$schemas = array_map(fn (TypeNode $member): array => $this->schema(node: $member), $members);
		$types   = [];
		foreach ($schemas as $schema) {
			if ($schema === []) {
				return [];
			}

			if (array_keys($schema) !== ['type'] || is_string($schema['type']) === false) {
				return $this->anyOf(schemas: $schemas);
			}

			$types[] = $schema['type'];
		}

		$types = array_values(array_unique($types));
		if (count($types) === 1) {
			return ['type' => $types[0]];
		}

		return ['type' => $types];
	}//end union()

	/**
	 * An `anyOf`, with `null` folded into a single `{type: null}` member.
	 *
	 * @param list<array<string, mixed>> $schemas The member schemas.
	 *
	 * @return array<string, mixed>
	 */
	private function anyOf(array $schemas): array {
		$unique = [];
		foreach ($schemas as $schema) {
			$unique[json_encode($schema)] = $schema;
		}

		return ['anyOf' => array_values($unique)];
	}//end anyOf()

	/**
	 * An array shape: an object with its keys, or a tuple when no key is named.
	 *
	 * @param ArrayShapeNode $node The shape.
	 *
	 * @return array<string, mixed>
	 */
	private function shape(ArrayShapeNode $node): array {
		$properties = [];
		$required   = [];
		foreach ($node->items as $item) {
			if ($item->keyName === null) {
				return ['type' => 'array'];
			}

			$key = (string) $item->keyName;
			if ($item->keyName instanceof ConstExprStringNode) {
				$key = $item->keyName->value;
			}

			$properties[$key] = (object) $this->schema(node: $item->valueType);
			if ($item->optional === false) {
				$required[] = $key;
			}
		}

		$schema = ['type' => 'object'];
		if ($properties !== []) {
			$schema['properties'] = $properties;
		}

		if ($required !== []) {
			$schema['required'] = $required;
		}

		$schema['additionalProperties'] = ($node->sealed === false);
		return $schema;
	}//end shape()

	/**
	 * A generic: `list<T>` and `array<int, T>` are arrays, `array<string, T>` is a map.
	 *
	 * @param GenericTypeNode $node The generic.
	 *
	 * @return array<string, mixed>
	 */
	private function generic(GenericTypeNode $node): array {
		$base = ltrim($node->type->name, '\\');
		if (in_array($base, ['array', 'list', 'non-empty-array', 'non-empty-list', 'iterable'], true) === false) {
			return $this->identifier(name: $node->type->name);
		}

		$args  = $node->genericTypes;
		$value = end($args);
		$items = (object) $this->schema(node: $value);
		if (count($args) === 2 && $this->isStringKey(node: $args[0]) === true) {
			return ['type' => 'object', 'additionalProperties' => $items];
		}

		return ['type' => 'array', 'items' => $items];
	}//end generic()

	/**
	 * Whether a key type is `string` (a map) rather than `int` (a list).
	 *
	 * @param TypeNode $node The key type.
	 *
	 * @return bool
	 */
	private function isStringKey(TypeNode $node): bool {
		return $node instanceof IdentifierTypeNode && in_array($node->name, ['string', 'non-empty-string', 'array-key'], true);
	}//end isStringKey()

	/**
	 * A constant type: a literal becomes a `const`.
	 *
	 * @param ConstTypeNode $node The constant.
	 *
	 * @return array<string, mixed>
	 */
	private function constant(ConstTypeNode $node): array {
		$expr = $node->constExpr;
		return match (true) {
			$expr instanceof ConstExprStringNode => ['type' => 'string', 'const' => $expr->value],
			$expr instanceof ConstExprIntegerNode => ['type' => 'integer', 'const' => (int) $expr->value],
			$expr instanceof ConstExprFloatNode => ['type' => 'number', 'const' => (float) $expr->value],
			$expr instanceof ConstExprTrueNode => ['type' => 'boolean', 'const' => true],
			$expr instanceof ConstExprFalseNode => ['type' => 'boolean', 'const' => false],
			default => [],
		};
	}//end constant()

	/**
	 * A named type: a scalar, a component alias, or `{}`.
	 *
	 * @param string $name The name as written.
	 *
	 * @return array<string, mixed>
	 */
	private function identifier(string $name): array {
		if (isset($this->aliases[$name]) === true) {
			return ['$ref' => '#/components/schemas/'.$name];
		}

		$type = (self::SCALARS[$name] ?? self::SCALARS[strtolower($name)] ?? null);
		if ($type === null) {
			return [];
		}

		if ($name === 'true' || $name === 'false') {
			return ['type' => 'boolean'];
		}

		return ['type' => $type];
	}//end identifier()
}//end class
