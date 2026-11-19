<?php
/**
 * OpenApiGenerator
 *
 * Builds `openapi.json` (OpenAPI 3.1) from `appinfo/routes.php` and the
 * controllers (governance-rest-api-reference D1): one operation per versioned
 * API route, with its parameters, request body, response codes and shapes,
 * authentication and `x-launchpad-stability`. The parts no code can say (the
 * document's introduction, shared components, wording an integrator needs on
 * a handful of operations) come from `scripts/openapi-overlay.json`.
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
 * Generates the OpenAPI document of LaunchPad's API.
 *
 * @spec openspec/specs/rest-api-reference/spec.md
 */
final class OpenApiGenerator {

	/**
	 * The OpenAPI version written.
	 *
	 * @var string
	 */
	public const OPENAPI_VERSION = '3.1.0';

	/**
	 * The verbs that carry a request body; the parameters of the others go in the query.
	 *
	 * @var list<string>
	 */
	private const BODY_VERBS = ['post', 'put', 'patch'];

	/**
	 * The keys that make an override a whole schema, replacing what it lands on.
	 *
	 * @var array<string, true>
	 */
	private const WHOLE_SCHEMAS = ['$ref' => true, 'allOf' => true, 'anyOf' => true, 'oneOf' => true];

	/**
	 * The key an operation carries its access level under until its description is closed.
	 *
	 * @var string
	 */
	private const ACCESS_KEY = 'x-access';

	/**
	 * The order verbs are written in under one path.
	 *
	 * @var list<string>
	 */
	private const VERB_ORDER = ['get', 'put', 'post', 'delete', 'patch', 'options', 'head'];

	/**
	 * The attributes that decide who may call a route.
	 *
	 * @var array<string, string>
	 */
	private const ATTRIBUTES = [
		'public'    => 'OCP\AppFramework\Http\Attribute\PublicPage',
		'user'      => 'OCP\AppFramework\Http\Attribute\NoAdminRequired',
		'delegated' => 'OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting',
	];

	/**
	 * The sentence each access level adds to an operation's description.
	 *
	 * @var array<string, string>
	 */
	private const ACCESS_SENTENCE = [
		'public'    => 'It needs no login.',
		'user'      => 'Needs a signed-in Nextcloud user.',
		'delegated' => 'Needs an administrator, or a user the matching admin setting is delegated to.',
		'admin'     => 'Needs an administrator.',
	];

	/**
	 * The default description of a success response.
	 *
	 * @var array<int, string>
	 */
	private const SUCCESS_DESCRIPTION = [
		200 => 'The request succeeded.',
		201 => 'The resource was created.',
		202 => 'The request was accepted.',
		204 => 'The request succeeded; there is no body.',
	];

	/**
	 * The reason phrases of the problem responses, by status code.
	 *
	 * @var array<int, string>
	 */
	private const REASONS = [
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		409 => 'Conflict',
		410 => 'Gone',
		413 => 'Content Too Large',
		415 => 'Unsupported Media Type',
		422 => 'Unprocessable Content',
		423 => 'Locked',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
	];

	/**
	 * Build a generator.
	 *
	 * @param OpenApiControllerReader $reader        Reads the controllers.
	 * @param OpenApiTypeMapper       $types         Maps parameter types.
	 * @param string                  $controllerDir The directory of the controllers.
	 * @param string                  $namespace     The namespace of the controllers.
	 */
	public function __construct(
		private readonly OpenApiControllerReader $reader,
		private readonly OpenApiTypeMapper $types,
		private readonly string $controllerDir,
		private readonly string $namespace,
	) {
	}//end __construct()

	/**
	 * The `Http::STATUS_*` constants of a copy of Nextcloud's `Http` class, by name.
	 *
	 * @param string $httpFile The path of OCP/AppFramework/Http.php.
	 *
	 * @return array<string, int>
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function statusCodes(string $httpFile): array {
		preg_match_all('/const\s+(STATUS_[A-Z_]+)\s*=\s*(\d+)\s*;/', (string) @file_get_contents($httpFile), $matches, PREG_SET_ORDER);
		$codes = [];
		foreach ($matches as $match) {
			$codes[$match[1]] = (int) $match[2];
		}

		return $codes;
	}//end statusCodes()

	/**
	 * The `@psalm-type` aliases of a definitions file, as component schemas.
	 *
	 * @param string            $file  The file holding the aliases.
	 * @param OpenApiTypeMapper $types A mapper that already knows the alias names.
	 *
	 * @return array<string, array<string, mixed>>
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function definitions(string $file, OpenApiTypeMapper $types): array {
		$schemas = [];
		foreach (self::aliasTypes(file: $file) as $name => $type) {
			$schemas[$name] = $types->schemaOf(type: $type);
		}

		return $schemas;
	}//end definitions()

	/**
	 * The `@psalm-type` aliases of a definitions file, name to type string.
	 *
	 * @param string $file The file holding the aliases.
	 *
	 * @return array<string, string>
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function aliasTypes(string $file): array {
		$source = (string) @file_get_contents($file);
		$types  = [];
		// One alias per tag: from `@psalm-type Name =` up to the next tag or the end of the docblock.
		preg_match_all('/@psalm-type\s+(\w+)\s*=\s*(.+?)(?=\n\s*\*\s*@|\n\s*\*\/)/s', $source, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$types[$match[1]] = trim((string) preg_replace('/\n\s*\*\s?/', ' ', $match[2]));
		}

		return $types;
	}//end aliasTypes()

	/**
	 * The document for a route table, with the overlay applied.
	 *
	 * @param array<string, mixed>                $routes      The route table, as appinfo/routes.php returns it.
	 * @param array<string, mixed>                $overlay     The hand-written parts.
	 * @param array<string, array<string, mixed>> $definitions The component schemas from `@psalm-type`.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/rest-api-reference/spec.md
	 */
	public function generate(array $routes, array $overlay, array $definitions=[]): array {
		$controllers = [];
		$paths       = [];
		$tags        = [];
		$overrides   = (array) ($overlay['operations'] ?? []);
		$usedIds     = [];

		foreach (($routes['routes'] ?? []) as $route) {
			$key = OpenApiRouteCoverage::routeKey(route: (array) $route);
			if ($key === null) {
				continue;
			}

			[$controller, $method] = explode('#', (string) $route['name'], 2);
			$read = ($controllers[$controller] ??= $this->readController(name: $controller));
			$info = ($read['methods'][$method] ?? null);
			if ($info === null) {
				throw new \RuntimeException(sprintf('route %s names %s, which has no public method', $key, (string) $route['name']));
			}

			$tag             = self::kebab(name: $controller);
			$tags[$tag]      = ($read['summary'] ?? '');
			$verb            = strtolower((string) ($route['verb'] ?? 'GET'));
			$path            = substr('/'.ltrim((string) $route['url'], '/'), strlen(OpenApiRouteCoverage::VERSIONED_PREFIX));
			$operation       = $this->operation(verb: $verb, path: $path, tag: $tag, method: $method, info: $info);
			$operation['operationId'] = self::uniqueId(id: $operation['operationId'], used: $usedIds);
			if (isset($overrides[$key]) === true) {
				$operation = self::merge(base: $operation, override: (array) $overrides[$key]);
			}

			$paths[$path][$verb] = self::withAccessSentence(operation: $operation);
		}

		return $this->document(paths: $paths, tags: $tags, overlay: $overlay, definitions: $definitions);
	}//end generate()

	/**
	 * The document encoded the way it is committed.
	 *
	 * @param array<string, mixed> $document The document.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function encode(array $document): string {
		return json_encode($document, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))."\n";
	}//end encode()

	/**
	 * A camelCase name in kebab-case: `dashboardApi` becomes `dashboard-api`.
	 *
	 * @param string $name The name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function kebab(string $name): string {
		return strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $name));
	}//end kebab()

	/**
	 * An override merged into an operation: objects merge key by key, anything else is replaced.
	 *
	 * A `$ref`, and an override that is itself a `$ref` or an `allOf`, `anyOf`
	 * or `oneOf`, is replaced whole: half of one schema merged into another
	 * describes neither.
	 *
	 * @param array<string, mixed> $base     The generated operation.
	 * @param array<string, mixed> $override The overlay's entry.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/archive/2026-11-19-governance-rest-api-reference/design.md
	 */
	public static function merge(array $base, array $override): array {
		foreach ($override as $key => $value) {
			$mergeable = (is_array($value) === true && array_is_list($value) === false
				&& isset($base[$key]) === true && is_array($base[$key]) === true && array_is_list($base[$key]) === false
				&& isset($base[$key]['$ref']) === false && array_intersect_key($value, self::WHOLE_SCHEMAS) === []);
			if ($mergeable === true) {
				$base[$key] = self::merge(base: $base[$key], override: $value);
				continue;
			}

			$base[$key] = $value;
		}

		return $base;
	}//end merge()

	/**
	 * An operation with the sentence on who may call it closing its description, and its responses in code order.
	 *
	 * The sentence follows the route's attributes, so an overlay that rewords
	 * the description cannot leave a stale statement about access behind.
	 *
	 * @param array<string, mixed> $operation The operation, still carrying its access level.
	 *
	 * @return array<string, mixed>
	 */
	private static function withAccessSentence(array $operation): array {
		$access = (string) $operation[self::ACCESS_KEY];
		unset($operation[self::ACCESS_KEY]);
		$operation['description'] = trim((string) $operation['description'].' '.self::ACCESS_SENTENCE[$access]);
		$responses = (array) $operation['responses'];
		ksort($responses, SORT_NATURAL);
		$operation['responses'] = $responses;
		return $operation;
	}//end withAccessSentence()

	/**
	 * An operation id not used before, suffixed with a number when it was.
	 *
	 * @param string              $id   The id.
	 * @param array<string, true> $used The ids used so far; the returned one is added.
	 *
	 * @return string
	 */
	private static function uniqueId(string $id, array &$used): string {
		$unique = $id;
		$suffix = 2;
		while (isset($used[$unique]) === true) {
			$unique = $id.'-'.$suffix;
			$suffix++;
		}

		$used[$unique] = true;
		return $unique;
	}//end uniqueId()

	/**
	 * Read the controller a route name points at.
	 *
	 * @param string $name The controller part of the route name (`dashboardApi`).
	 *
	 * @return array<string, mixed>
	 */
	private function readController(string $name): array {
		$file = $this->controllerDir.'/'.ucfirst($name).'Controller.php';
		$read = $this->reader->read(file: $file);
		if ($read === null) {
			throw new \RuntimeException(sprintf('no controller class in %s', $file));
		}

		return $read;
	}//end readController()

	/**
	 * One operation.
	 *
	 * @param string               $verb   The lower-case verb.
	 * @param string               $path   The path below the versioned prefix.
	 * @param string               $tag    The tag (the controller in kebab-case).
	 * @param string               $method The controller method.
	 * @param array<string, mixed> $info   What the reader found on the method.
	 *
	 * @return array<string, mixed>
	 */
	private function operation(string $verb, string $path, string $tag, string $method, array $info): array {
		$access    = $this->access(attributes: (array) $info['attributes']);
		$text      = (string) $info['text'];
		$summary   = $this->reader->firstSentence(text: $text);
		$stability = (string) ($info['stability'] ?? 'internal');
		if (in_array($stability, OpenApiRouteCoverage::STABILITY, true) === false) {
			$stability = 'internal';
		}

		if ($summary === '') {
			$summary = $method;
		}

		$operation = [
			'operationId'           => $tag.'-'.self::kebab(name: $method),
			'x-launchpad-stability' => $stability,
			'summary'               => $summary,
			'description'           => str_replace("\n\n", ' ', $text),
			'tags'                  => [$tag],
			self::ACCESS_KEY        => $access,
		];
		if ($access === 'public') {
			$operation['security'] = [];
		}

		[$parameters, $body] = $this->parameters(verb: $verb, path: $path, params: (array) $info['params']);
		if ($parameters !== []) {
			$operation['parameters'] = $parameters;
		}

		if ($body !== null) {
			$operation['requestBody'] = $body;
		}

		$operation['responses'] = $this->responses(access: $access, codes: (array) $info['codes'], shapes: (array) $info['responses']);
		return $operation;
	}//end operation()

	/**
	 * Who may call a method: public, user, delegated or admin.
	 *
	 * @param list<string> $attributes The method's attributes.
	 *
	 * @return string
	 */
	private function access(array $attributes): string {
		foreach (self::ATTRIBUTES as $access => $attribute) {
			if (in_array($attribute, $attributes, true) === true) {
				return $access;
			}
		}

		return 'admin';
	}//end access()

	/**
	 * The parameters (path and query) and the request body of an operation.
	 *
	 * @param string                           $verb   The lower-case verb.
	 * @param string                           $path   The path.
	 * @param list<array<string, mixed>>       $params The method's parameters.
	 *
	 * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
	 */
	private function parameters(string $verb, string $path, array $params): array {
		preg_match_all('/\{(\w+)\}/', $path, $matches);
		$inPath     = $matches[1];
		$byName     = array_column($params, null, 'name');
		$parameters = [];
		foreach ($inPath as $name) {
			$param        = ($byName[$name] ?? ['name' => $name, 'native' => 'string', 'docType' => null, 'description' => '']);
			$parameters[] = $this->parameter(param: $param, in: 'path', required: true);
		}

		$properties = [];
		$required   = [];
		foreach ($params as $param) {
			if (in_array($param['name'], $inPath, true) === true) {
				continue;
			}

			if (in_array($verb, self::BODY_VERBS, true) === false) {
				$parameters[] = $this->parameter(param: $param, in: 'query', required: ($param['optional'] === false));
				continue;
			}

			$properties[$param['name']] = (object) $this->describe(schema: $this->paramSchema(param: $param), description: (string) $param['description']);
			if ($param['optional'] === false) {
				$required[] = $param['name'];
			}
		}

		if ($properties === []) {
			return [$parameters, null];
		}

		$schema = ['type' => 'object', 'properties' => $properties];
		if ($required !== []) {
			$schema['required'] = $required;
		}

		$body = [
			'required' => ($required !== []),
			'content'  => ['application/json' => ['schema' => $schema]],
		];
		return [$parameters, $body];
	}//end parameters()

	/**
	 * One path or query parameter.
	 *
	 * @param array<string, mixed> $param    The method parameter.
	 * @param string               $in       `path` or `query`.
	 * @param bool                 $required Whether it must be given.
	 *
	 * @return array<string, mixed>
	 */
	private function parameter(array $param, string $in, bool $required): array {
		$schema = $this->paramSchema(param: $param);
		$type   = ($schema['type'] ?? null);
		$plain  = (is_string($type) === true && in_array($type, ['string', 'integer', 'number', 'boolean'], true) === true);
		if ($in === 'path' || $plain === false) {
			$schema = $this->scalarOf(schema: $schema);
		}

		$parameter = ['name' => (string) $param['name'], 'in' => $in, 'required' => $required];
		if ((string) ($param['description'] ?? '') !== '') {
			$parameter['description'] = (string) $param['description'];
		}

		if (($schema['type'] ?? null) === 'string' && preg_match('/(^uuid|Uuid)$/', (string) $param['name']) === 1) {
			$schema['format'] = 'uuid';
		}

		$parameter['schema'] = (object) $schema;
		return $parameter;
	}//end parameter()

	/**
	 * The scalar a path or query value is sent as: the first non-null scalar of the schema, else a string.
	 *
	 * @param array<string, mixed> $schema The parameter's schema.
	 *
	 * @return array<string, mixed>
	 */
	private function scalarOf(array $schema): array {
		$types = (array) ($schema['type'] ?? []);
		foreach ($types as $type) {
			if (in_array($type, ['string', 'integer', 'number', 'boolean'], true) === true) {
				return ['type' => $type];
			}
		}

		return ['type' => 'string'];
	}//end scalarOf()

	/**
	 * The schema of a method parameter: its `@param` type, else its native type.
	 *
	 * @param array<string, mixed> $param The parameter.
	 *
	 * @return array<string, mixed>
	 */
	private function paramSchema(array $param): array {
		foreach ([($param['docType'] ?? null), ($param['native'] ?? null)] as $type) {
			if (is_string($type) === false || $type === '') {
				continue;
			}

			$schema = $this->types->schemaOf(type: $type);
			if ($schema !== []) {
				return $schema;
			}
		}

		return [];
	}//end paramSchema()

	/**
	 * A schema with a description added, when there is one.
	 *
	 * @param array<string, mixed> $schema      The schema.
	 * @param string               $description The description.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(array $schema, string $description): array {
		if ($description !== '') {
			$schema['description'] = $description;
		}

		return $schema;
	}//end describe()

	/**
	 * The responses of an operation.
	 *
	 * Declared `@psalm-return` shapes win. Otherwise the 2xx codes the method
	 * reaches are its successes (200 when it names none) and every 4xx or 5xx
	 * code it reaches is a problem response. A route behind a login also
	 * answers 401, an admin route also 403.
	 *
	 * @param string                           $access The access level.
	 * @param list<int>                        $codes  The codes the method reaches.
	 * @param array<int, array<string, mixed>> $shapes The declared response bodies.
	 *
	 * @return array<string, mixed>
	 */
	private function responses(string $access, array $codes, array $shapes): array {
		$all = $codes;
		if ($shapes !== []) {
			// Declared shapes say which successes there are; a 2xx the body
			// scan found in a shared helper is another route's.
			$all = array_values(array_filter($codes, static fn (int $code): bool => $code >= 300));
		}

		$all = array_merge($all, array_keys($shapes));
		if ($access !== 'public') {
			$all[] = 401;
		}

		if ($access === 'admin' || $access === 'delegated') {
			$all[] = 403;
		}

		$success = array_filter($all, static fn (int $code): bool => $code >= 200 && $code < 300);
		if ($success === [] && $shapes === []) {
			$all[] = 200;
		}

		$all = array_values(array_unique(array_filter($all, static fn (int $code): bool => $code >= 200 && $code < 600)));
		sort($all);

		$responses = [];
		foreach ($all as $code) {
			if ($code >= 400) {
				$responses[(string) $code] = $this->problem(code: $code, schema: ($shapes[$code] ?? null));
				continue;
			}

			if ($code >= 300) {
				continue;
			}

			$responses[(string) $code] = $this->success(code: $code, schema: ($shapes[$code] ?? []));
		}

		return $responses;
	}//end responses()

	/**
	 * A problem response: the shared one, or the problem document extended with a declared body.
	 *
	 * @param int                       $code   The status code.
	 * @param array<string, mixed>|null $schema The declared body, or null.
	 *
	 * @return array<string, mixed>
	 */
	private function problem(int $code, ?array $schema): array {
		if ($schema === null || $schema === []) {
			return ['$ref' => '#/components/responses/Problem'.$code];
		}

		return [
			'description' => (self::REASONS[$code] ?? 'Error').'.',
			'content'     => [
				'application/problem+json' => [
					'schema' => ['allOf' => [['$ref' => '#/components/schemas/Problem'], $schema]],
				],
			],
		];
	}//end problem()

	/**
	 * A success response.
	 *
	 * @param int                  $code   The status code.
	 * @param array<string, mixed> $schema The body schema; `{}` when not declared.
	 *
	 * @return array<string, mixed>
	 */
	private function success(int $code, array $schema): array {
		$response = [
			'description' => (self::SUCCESS_DESCRIPTION[$code] ?? 'The request succeeded.'),
			'headers'     => ['API-Version' => ['$ref' => '#/components/headers/API-Version']],
		];
		if ($code !== 204) {
			$response['content'] = ['application/json' => ['schema' => (object) $schema]];
		}

		return $response;
	}//end success()

	/**
	 * The whole document: the overlay's frame, the tags, the paths and the components.
	 *
	 * @param array<string, array<string, mixed>> $paths       The operations by path and verb.
	 * @param array<string, string>               $tags        The tag descriptions.
	 * @param array<string, mixed>                $overlay     The hand-written parts.
	 * @param array<string, array<string, mixed>> $definitions The `@psalm-type` schemas.
	 *
	 * @return array<string, mixed>
	 */
	private function document(array $paths, array $tags, array $overlay, array $definitions): array {
		ksort($paths);
		foreach ($paths as $path => $item) {
			uksort(
				$item,
				static fn (string $left, string $right): int => (array_search($left, self::VERB_ORDER, true) <=> array_search($right, self::VERB_ORDER, true))
			);
			$paths[$path] = $item;
		}

		ksort($tags);
		$tagList = [];
		foreach ($tags as $name => $description) {
			$tag = ['name' => $name];
			if ($description !== '') {
				$tag['description'] = $description.'.';
			}

			$tagList[] = $tag;
		}

		$components            = (array) ($overlay['components'] ?? []);
		$components['schemas'] = array_merge((array) ($components['schemas'] ?? []), $definitions);
		$components['responses'] = $this->problemResponses(paths: $paths, existing: (array) ($components['responses'] ?? []));

		$document = [
			'openapi' => self::OPENAPI_VERSION,
			'info'    => ($overlay['info'] ?? ['title' => 'LaunchPad', 'version' => '1']),
		];
		foreach (['servers', 'security'] as $key) {
			if (isset($overlay[$key]) === true) {
				$document[$key] = $overlay[$key];
			}
		}

		$document['tags']       = $tagList;
		$document['paths']      = $paths;
		$document['components'] = $components;
		return $document;
	}//end document()

	/**
	 * The problem responses the operations refer to: the overlay's, plus a plain one for each other code used.
	 *
	 * @param array<string, array<string, mixed>> $paths    The operations.
	 * @param array<string, mixed>                $existing The overlay's responses.
	 *
	 * @return array<string, mixed>
	 */
	private function problemResponses(array $paths, array $existing): array {
		$responses = $existing;
		foreach ($paths as $item) {
			foreach ($item as $operation) {
				foreach (array_keys((array) ($operation['responses'] ?? [])) as $code) {
					$name = 'Problem'.$code;
					if ((int) $code < 400 || isset($responses[$name]) === true) {
						continue;
					}

					$responses[$name] = [
						'description' => (self::REASONS[(int) $code] ?? 'Error').'.',
						'content'     => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]],
					];
				}
			}
		}

		ksort($responses, SORT_NATURAL);
		return $responses;
	}//end problemResponses()
}//end class
