<?php
/**
 * OpenApiRouteCoverage
 *
 * The route half of the `composer lint:openapi` drift check
 * (governance-rest-api-reference D3): every versioned API route in
 * `appinfo/routes.php` has an operation in `openapi.json`, every operation
 * carries `x-launchpad-stability` (public or internal), and no operation is
 * documented for a route that does not exist.
 *
 * Path parameters are compared by position, not by name, so `{id}` in a route
 * and `{uuid}` in the document are the same segment. The catch-all API
 * fallback route is not an operation and is left out.
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
 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Scripts;

use OCA\LaunchPad\Middleware\Support\ApiPathAliases;

/**
 * Compares the route table with the OpenAPI document.
 *
 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
 */
final class OpenApiRouteCoverage {

	/**
	 * The prefix of every versioned API route; the document's server URL ends in it.
	 *
	 * @var string
	 */
	public const VERSIONED_PREFIX = '/api/v1';

	/**
	 * The stability levels an operation may carry.
	 *
	 * @var list<string>
	 */
	public const STABILITY = ['public', 'internal'];

	/**
	 * The problems found, one line each; empty when the document covers the routes.
	 *
	 * @param array<string, mixed> $routes   The route table, as appinfo/routes.php returns it.
	 * @param array<string, mixed> $document The decoded openapi.json.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
	 */
	public static function problems(array $routes, array $document): array {
		$operations = self::operations(document: $document);
		$routed     = [];
		$problems   = [];

		foreach (($routes['routes'] ?? []) as $route) {
			$key = self::routeKey(route: (array) $route);
			if ($key === null) {
				continue;
			}

			$routed[$key] = true;
			if (array_key_exists($key, $operations) === false) {
				$problems[] = sprintf('undocumented route: %s (%s)', $key, (string) ($route['name'] ?? '?'));
			}
		}

		foreach ($operations as $key => $stability) {
			if (in_array($stability, self::STABILITY, true) === false) {
				$problems[] = sprintf('no x-launchpad-stability (public or internal): %s', $key);
			}

			if (isset($routed[$key]) === false) {
				$problems[] = sprintf('documented operation without a route: %s', $key);
			}
		}

		return $problems;
	}//end problems()

	/**
	 * Every operation of the document by "VERB /path", with its stability.
	 *
	 * @param array<string, mixed> $document The decoded openapi.json.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
	 */
	public static function operations(array $document): array {
		$operations = [];
		foreach ((array) ($document['paths'] ?? []) as $path => $item) {
			foreach ((array) $item as $verb => $operation) {
				if (is_array($operation) === false || isset($operation['responses']) === false) {
					continue;
				}

				$operations[strtoupper((string) $verb).' '.self::normalise(path: (string) $path)] = ($operation['x-launchpad-stability'] ?? null);
			}
		}

		return $operations;
	}//end operations()

	/**
	 * The "VERB /path" a route is documented under, or null when it is not an API operation.
	 *
	 * @param array<string, mixed> $route One route entry.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
	 */
	public static function routeKey(array $route): ?string {
		$url = '/'.ltrim((string) ($route['url'] ?? ''), '/');
		if (str_starts_with($url, self::VERSIONED_PREFIX.'/') === false) {
			return null;
		}

		$relative = substr($url, strlen(self::VERSIONED_PREFIX));
		$fallback = ['url' => '/api'.$relative, 'requirements' => ($route['requirements'] ?? [])];
		if (ApiPathAliases::isApiFallback(route: $fallback) === true) {
			return null;
		}

		return strtoupper((string) ($route['verb'] ?? 'GET')).' '.self::normalise(path: $relative);
	}//end routeKey()

	/**
	 * A path with every parameter written as `{}` and no trailing slash.
	 *
	 * @param string $path The path.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
	 */
	public static function normalise(string $path): string {
		$trimmed = rtrim($path, '/');
		if ($trimmed === '') {
			$trimmed = '/';
		}

		return (string) preg_replace('#\{[^}]+\}#', '{}', $trimmed);
	}//end normalise()
}//end class
