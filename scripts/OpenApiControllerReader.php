<?php
/**
 * OpenApiControllerReader
 *
 * Reads a controller source file without loading it: the class summary and,
 * per public method, its attributes, parameters, docblock text, `@param`
 * descriptions, `@psalm-return` response shapes, `@openapi-stability` tag and
 * the HTTP status codes it can answer with. Static parsing keeps the
 * generator independent of a Nextcloud runtime (governance-rest-api-reference
 * D1).
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
 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Scripts;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Reads what the OpenAPI generator needs from a controller's source.
 *
 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
 */
final class OpenApiControllerReader {

	/**
	 * The fully qualified name of Nextcloud's `Http` class, whose `STATUS_*` constants are status codes.
	 *
	 * @var string
	 */
	private const HTTP_CLASS = 'OCP\AppFramework\Http';

	/**
	 * The named arguments that carry a status code.
	 *
	 * @var list<string>
	 */
	private const STATUS_ARGUMENTS = ['statusCode', 'status', 'code'];

	/**
	 * The calls that refuse with 403: the ADR-023 action check.
	 *
	 * @var list<string>
	 */
	private const FORBIDDING_CALLS = ['actionRefusal', 'requireAction'];

	/**
	 * The PHPDoc parser.
	 *
	 * @var PhpDocParser
	 */
	private PhpDocParser $docParser;

	/**
	 * The PHPDoc lexer.
	 *
	 * @var Lexer
	 */
	private Lexer $lexer;

	/**
	 * Build a reader.
	 *
	 * @param OpenApiTypeMapper  $types       Maps the `@psalm-return` shapes.
	 * @param array<string, int> $statusCodes The `Http::STATUS_*` names and their codes.
	 */
	public function __construct(
		private readonly OpenApiTypeMapper $types,
		private readonly array $statusCodes,
	) {
		$config          = new ParserConfig(usedAttributes: []);
		$constants       = new ConstExprParser(config: $config);
		$this->lexer     = new Lexer(config: $config);
		$this->docParser = new PhpDocParser(
			config: $config,
			typeParser: new TypeParser(config: $config, constExprParser: $constants),
			constantExprParser: $constants
		);
	}//end __construct()

	/**
	 * Read one controller file; null when it holds no class.
	 *
	 * @param string $file The path of the PHP file.
	 *
	 * @return array{class: string, summary: string, methods: array<string, array<string, mixed>>}|null
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public function read(string $file): ?array {
		$parser = (new ParserFactory())->createForHostVersion();
		$stmts  = $parser->parse((string) file_get_contents($file));
		if ($stmts === null) {
			return null;
		}

		$traverser = new NodeTraverser(new NameResolver());
		$stmts     = $traverser->traverse($stmts);
		$class     = (new NodeFinder())->findFirstInstanceOf($stmts, Class_::class);
		if ($class instanceof Class_ === false || $class->namespacedName === null) {
			return null;
		}

		$bodies = [];
		foreach ($class->getMethods() as $method) {
			$bodies[$method->name->toString()] = $this->scanBody(method: $method);
		}

		$methods = [];
		foreach ($class->getMethods() as $method) {
			if ($method->isPublic() === false || $method->isStatic() === true || $method->name->toString() === '__construct') {
				continue;
			}

			$methods[$method->name->toString()] = $this->readMethod(
				method: $method,
				codes: $this->reachableCodes(name: $method->name->toString(), bodies: $bodies),
				requestParams: $this->reachableRequestParams(name: $method->name->toString(), bodies: $bodies)
			);
		}

		return [
			'class'   => $class->namespacedName->toString(),
			'summary' => $this->firstSentence(text: $this->docText(doc: $this->doc(node: $class))),
			'methods' => $methods,
		];
	}//end read()

	/**
	 * The first sentence of a text, without its closing full stop.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public function firstSentence(string $text): string {
		$paragraph = trim(explode("\n\n", $text)[0]);
		$paragraph = (string) preg_replace('/\s+/', ' ', $paragraph);
		if (preg_match('/^(.+?[.!?])(\s|$)/u', $paragraph, $match) === 1) {
			$paragraph = $match[1];
		}

		return rtrim($paragraph, '.');
	}//end firstSentence()

	/**
	 * What the generator needs of one public method.
	 *
	 * @param ClassMethod                         $method        The method.
	 * @param list<int>                           $codes         The status codes it can answer with.
	 * @param array<string, array<string, mixed>> $requestParams The parameters it reads through `getParam()`.
	 *
	 * @return array<string, mixed>
	 */
	private function readMethod(ClassMethod $method, array $codes, array $requestParams): array {
		$doc        = $this->doc(node: $method);
		$paramDocs  = [];
		foreach (($doc?->getParamTagValues() ?? []) as $tag) {
			$paramDocs[ltrim($tag->parameterName, '$')] = [
				'type'        => (string) $tag->type,
				'description' => $this->collapse(text: $tag->description),
			];
		}

		$params = [];
		foreach ($method->params as $param) {
			if ($param->var instanceof Variable === false || is_string($param->var->name) === false) {
				continue;
			}

			$name     = $param->var->name;
			$params[] = [
				'name'        => $name,
				'native'      => $this->nativeType(type: $param->type),
				'optional'    => ($param->default !== null || $param->variadic === true),
				'docType'     => ($paramDocs[$name]['type'] ?? null),
				'description' => ($paramDocs[$name]['description'] ?? ''),
			];
		}

		$named = array_column($params, 'name');
		foreach ($requestParams as $name => $read) {
			if (in_array($name, $named, true) === true) {
				continue;
			}

			$params[] = [
				'name'        => $name,
				'native'      => $read['type'],
				'optional'    => true,
				'docType'     => null,
				'description' => '',
			];
		}

		return [
			'attributes' => $this->attributes(method: $method),
			'params'     => $params,
			'text'       => $this->docText(doc: $doc),
			'responses'  => $this->responses(doc: $doc),
			'stability'  => $this->tagValue(doc: $doc, name: '@openapi-stability'),
			'codes'      => $codes,
		];
	}//end readMethod()

	/**
	 * The fully qualified names of a method's attributes.
	 *
	 * @param ClassMethod $method The method.
	 *
	 * @return list<string>
	 */
	private function attributes(ClassMethod $method): array {
		$names = [];
		foreach ($method->attrGroups as $group) {
			foreach ($group->attrs as $attribute) {
				$names[] = $attribute->name->toString();
			}
		}

		return $names;
	}//end attributes()

	/**
	 * A native parameter type as written (`?string`, `int|null`), or null when untyped.
	 *
	 * @param Node|null $type The type node.
	 *
	 * @return string|null
	 */
	private function nativeType(?Node $type): ?string {
		return match (true) {
			$type === null => null,
			$type instanceof Node\NullableType => '?'.$this->nativeType(type: $type->type),
			$type instanceof Node\UnionType => implode('|', array_map(fn (Node $member): string => (string) $this->nativeType(type: $member), $type->types)),
			$type instanceof Identifier => $type->toString(),
			$type instanceof Name => $type->toString(),
			default => null,
		};
	}//end nativeType()

	/**
	 * The response schemas a `@psalm-return` (or `@return`) union of `JSONResponse<status, body, headers>` declares.
	 *
	 * @param PhpDocNode|null $doc The docblock.
	 *
	 * @return array<int, array<string, mixed>> Status code to body schema; empty when none is declared.
	 */
	private function responses(?PhpDocNode $doc): array {
		$tags = ($doc?->getReturnTagValues(tagName: '@psalm-return') ?? []);
		if ($tags === []) {
			$tags = ($doc?->getReturnTagValues() ?? []);
		}

		$responses = [];
		foreach ($tags as $tag) {
			if ($tag instanceof ReturnTagValueNode === false) {
				continue;
			}

			foreach ($this->members(type: $tag->type) as $member) {
				if ($member instanceof GenericTypeNode === false || count($member->genericTypes) < 2) {
					continue;
				}

				$status = $this->types->statusOf(node: $member->genericTypes[0]);
				if ($status !== null) {
					$responses[$status] = $this->types->schema(node: $member->genericTypes[1]);
				}
			}
		}

		ksort($responses);
		return $responses;
	}//end responses()

	/**
	 * The members of a union, or the type itself.
	 *
	 * @param TypeNode $type The type.
	 *
	 * @return list<TypeNode>
	 */
	private function members(TypeNode $type): array {
		if ($type instanceof UnionTypeNode) {
			return array_values($type->types);
		}

		return [$type];
	}//end members()

	/**
	 * The status codes in a method body and the `$this->...()` methods it calls.
	 *
	 * @param ClassMethod $method The method.
	 *
	 * @return array{codes: list<int>, calls: list<string>, requestParams: array<string, array<string, mixed>>}
	 */
	private function scanBody(ClassMethod $method): array {
		$finder = new NodeFinder();
		$codes  = [];
		foreach ($finder->findInstanceOf((array) $method->stmts, ClassConstFetch::class) as $fetch) {
			if ($fetch->class instanceof Name && $fetch->class->toString() === self::HTTP_CLASS && $fetch->name instanceof Identifier) {
				$code = ($this->statusCodes[$fetch->name->toString()] ?? null);
				if ($code !== null) {
					$codes[] = $code;
				}
			}
		}

		foreach ($finder->findInstanceOf((array) $method->stmts, Arg::class) as $arg) {
			if ($arg->name !== null && in_array($arg->name->toString(), self::STATUS_ARGUMENTS, true) === true && $arg->value instanceof Int_) {
				$codes[] = $arg->value->value;
			}
		}

		$calls = [];
		foreach ($finder->findInstanceOf((array) $method->stmts, MethodCall::class) as $call) {
			if ($call->var instanceof Variable && $call->var->name === 'this' && $call->name instanceof Identifier) {
				$calls[] = $call->name->toString();
			}
		}

		$requestParams = [];
		foreach ($finder->findInstanceOf((array) $method->stmts, MethodCall::class) as $call) {
			if ($call->name instanceof Identifier === false) {
				continue;
			}

			if (in_array($call->name->toString(), self::FORBIDDING_CALLS, true) === true) {
				$codes[] = 403;
			}

			if ($call->name->toString() === 'getParam') {
				$requestParams += $this->requestParam(call: $call);
			}
		}

		return ['codes' => $codes, 'calls' => array_values(array_unique($calls)), 'requestParams' => $requestParams];
	}//end scanBody()

	/**
	 * The parameter a `$this->request->getParam('name', default)` call reads.
	 *
	 * @param MethodCall $call The call.
	 *
	 * @return array<string, array{optional: bool, type: string|null}> Empty when the name is not a literal.
	 */
	private function requestParam(MethodCall $call): array {
		$key     = null;
		$default = null;
		foreach ($call->getArgs() as $position => $arg) {
			$name = ($arg->name?->toString() ?? ['key', 'default'][$position] ?? '');
			if ($name === 'key' && $arg->value instanceof Node\Scalar\String_) {
				$key = $arg->value->value;
			}

			if ($name === 'default') {
				$default = $arg->value;
			}
		}

		if ($key === null) {
			return [];
		}

		$type = match (true) {
			$default instanceof Node\Expr\Array_ => 'array',
			$default instanceof Int_ => 'int',
			$default instanceof Node\Scalar\Float_ => 'float',
			$default instanceof Node\Scalar\String_ => 'string',
			$default instanceof Node\Expr\ConstFetch && in_array(strtolower($default->name->toString()), ['true', 'false'], true) => 'bool',
			default => null,
		};

		return [$key => ['optional' => true, 'type' => $type]];
	}//end requestParam()

	/**
	 * The status codes a method reaches, through the methods of its own class it calls.
	 *
	 * @param string                                                     $name   The method.
	 * @param array<string, array{codes: list<int>, calls: list<string>}> $bodies Every method's scan.
	 *
	 * @return list<int>
	 */
	private function reachableCodes(string $name, array $bodies): array {
		$seen  = [];
		$queue = [$name];
		$codes = [];
		while ($queue !== []) {
			$current = array_shift($queue);
			if (isset($seen[$current]) === true || isset($bodies[$current]) === false) {
				continue;
			}

			$seen[$current] = true;
			$codes          = array_merge($codes, $bodies[$current]['codes']);
			$queue          = array_merge($queue, $bodies[$current]['calls']);
		}

		$codes = array_values(array_unique($codes));
		sort($codes);
		return $codes;
	}//end reachableCodes()

	/**
	 * The request parameters a method reads through `getParam()`, itself or through the methods of its class it calls.
	 *
	 * @param string                              $name   The method.
	 * @param array<string, array<string, mixed>> $bodies Every method's scan.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function reachableRequestParams(string $name, array $bodies): array {
		$seen   = [];
		$queue  = [$name];
		$params = [];
		while ($queue !== []) {
			$current = array_shift($queue);
			if (isset($seen[$current]) === true || isset($bodies[$current]) === false) {
				continue;
			}

			$seen[$current] = true;
			$params        += $bodies[$current]['requestParams'];
			$queue          = array_merge($queue, $bodies[$current]['calls']);
		}

		return $params;
	}//end reachableRequestParams()

	/**
	 * The parsed docblock of a node, or null.
	 *
	 * @param Node $node The class or method.
	 *
	 * @return PhpDocNode|null
	 */
	private function doc(Node $node): ?PhpDocNode {
		$comment = $node->getDocComment();
		if ($comment === null) {
			return null;
		}

		// A union wrapped after its `|` continues on the next line; join it so the type parses whole.
		$text   = (string) preg_replace('/\|[ \t]*\n[ \t]*\*[ \t]*/', '|', $comment->getText());
		$tokens = new TokenIterator(tokens: $this->lexer->tokenize(s: $text));
		return $this->docParser->parse(tokens: $tokens);
	}//end doc()

	/**
	 * The prose of a docblock before its first tag, paragraphs kept, lines joined.
	 *
	 * @param PhpDocNode|null $doc The docblock.
	 *
	 * @return string
	 */
	private function docText(?PhpDocNode $doc): string {
		$lines = [];
		foreach (($doc?->children ?? []) as $child) {
			if ($child instanceof PhpDocTagNode) {
				break;
			}

			if ($child instanceof PhpDocTextNode) {
				$lines[] = $child->text;
			}
		}

		$paragraphs = preg_split('/\n\s*\n/', trim(implode("\n", $lines)));
		$paragraphs = array_map(fn (string $paragraph): string => $this->collapse(text: $paragraph), (array) $paragraphs);
		return implode("\n\n", array_filter($paragraphs, static fn (string $paragraph): bool => $paragraph !== ''));
	}//end docText()

	/**
	 * The value of a custom tag such as `@openapi-stability`, or null.
	 *
	 * @param PhpDocNode|null $doc  The docblock.
	 * @param string          $name The tag, with its `@`.
	 *
	 * @return string|null
	 */
	private function tagValue(?PhpDocNode $doc, string $name): ?string {
		foreach (($doc?->getTagsByName(tagName: $name) ?? []) as $tag) {
			if ($tag->value instanceof GenericTagValueNode) {
				return trim($tag->value->value);
			}
		}

		return null;
	}//end tagValue()

	/**
	 * A text with its whitespace runs collapsed to single spaces.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	private function collapse(string $text): string {
		$text = (string) preg_replace('/\{@(?:see|link)\s+([^}\s]+)\s*\}/', '`$1`', $text);
		return trim((string) preg_replace('/\s+/', ' ', $text));
	}//end collapse()
}//end class
