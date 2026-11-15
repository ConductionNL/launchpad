<?php

/**
 * ScopeEnforcementService
 *
 * What an embed token's scope allows at the API tier (REQ-EMB-004), decided
 * before any controller runs: a `read` token may only GET or HEAD; a
 * `read-with-interactions` token may also run the actions it names in
 * `allowedActions`. The token's `subject.filterContext` is pinned over any
 * filter the request carries. The decisions are pure; EmbedTokenMiddleware
 * applies them before any controller runs.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

/**
 * Decides whether a request fits an embed token's scope.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
 */
final class ScopeEnforcementService {

	/**
	 * A token that only reads.
	 *
	 * @var string
	 */
	public const MODE_READ = 'read';

	/**
	 * A token that reads and runs the actions it names.
	 *
	 * @var string
	 */
	public const MODE_INTERACT = 'read-with-interactions';

	/**
	 * Methods that never change state.
	 *
	 * @var array<int, string>
	 */
	private const SAFE_METHODS = ['GET', 'HEAD'];

	/**
	 * The refusal of a request the scope does not allow, or null when it may run.
	 *
	 * Any write under a `read` token is refused, whatever the operation (4.6);
	 * under `read-with-interactions` a write must name an allowed action. An
	 * unknown mode is treated as `read`: the stricter reading.
	 *
	 * @param string               $method The HTTP method.
	 * @param string|null          $action The action the request runs, if any.
	 * @param array<string, mixed> $scope  The token's scope (`mode`, `allowedActions`).
	 *
	 * @return array{status: int, body: array<string, string>}|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function refusalFor(string $method, ?string $action, array $scope): ?array {
		if (in_array(strtoupper($method), self::SAFE_METHODS, true) === true) {
			return null;
		}

		$mode = (string)($scope['mode'] ?? self::MODE_READ);
		if ($mode !== self::MODE_INTERACT) {
			return [
				'status' => 403,
				'body' => [
					'error' => 'read_only_token',
					'message' => 'This token does not permit write operations. Token scope: mode=read',
				],
			];
		}

		return $this->enforceActionScope(action: (string)$action, scope: $scope);
	}//end refusalFor()

	/**
	 * The refusal of an action the scope does not name, or null.
	 *
	 * @param string               $action The action.
	 * @param array<string, mixed> $scope  The token's scope.
	 *
	 * @return array{status: int, body: array<string, string>}|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function enforceActionScope(string $action, array $scope): ?array {
		$allowed = array_values(array_filter(
			array_map('strval', (array)($scope['allowedActions'] ?? [])),
			static fn (string $name): bool => $name !== ''
		));
		if ($action !== '' && in_array($action, $allowed, true) === true) {
			return null;
		}

		$list = 'none';
		if ($allowed !== []) {
			$list = implode(', ', $allowed);
		}

		return [
			'status' => 403,
			'body' => [
				'error' => 'action_not_in_scope',
				'message' => sprintf('The action \'%s\' is not permitted by this token. Allowed actions: %s', $action, $list),
			],
		];
	}//end enforceActionScope()

	/**
	 * The request's filters with the token's filter context pinned over them.
	 *
	 * @param array<string, mixed> $requestFilters The filters the request asks for.
	 * @param array<string, mixed> $filterContext  The token's `subject.filterContext`.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function applyFilterContext(array $requestFilters, array $filterContext): array {
		return array_merge($requestFilters, $filterContext);
	}//end applyFilterContext()

	/**
	 * The request's filters on the dimensions the scope allows, with the
	 * token's filter context pinned over them. A filter on a dimension outside
	 * `allowedFilters` is dropped; a `read` token, or one without
	 * `allowedFilters`, keeps only the pinned context (4.9).
	 *
	 * @param array<string, mixed> $requestFilters The filters the request asks for.
	 * @param array<string, mixed> $scope          The token's scope.
	 * @param array<string, mixed> $filterContext  The token's `subject.filterContext`.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function restrictFilters(array $requestFilters, array $scope, array $filterContext): array {
		$allowed = [];
		if ((string)($scope['mode'] ?? self::MODE_READ) === self::MODE_INTERACT) {
			$allowed = array_map('strval', (array)($scope['allowedFilters'] ?? []));
		}

		$kept = array_filter(
			$requestFilters,
			static fn (mixed $value, int|string $dimension): bool => in_array((string)$dimension, $allowed, true) === true,
			ARRAY_FILTER_USE_BOTH
		);

		return $this->applyFilterContext(requestFilters: $kept, filterContext: $filterContext);
	}//end restrictFilters()

	/**
	 * The scope action a write request runs, read from its last path segment:
	 * `export` is export, `drill-down` (or `drilldown`) is drillDown, and
	 * `filters` or `interactions` is interact. Anything else names no action,
	 * so an interactions token refuses it too.
	 *
	 * @param string $path The request path.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function actionOf(string $path): ?string {
		$segments = explode('/', trim($path, '/'));
		$last     = strtolower((string)end($segments));

		return match ($last) {
			'export' => 'export',
			'drill-down', 'drilldown' => 'drillDown',
			'filters', 'interactions' => 'interact',
			default => null,
		};
	}//end actionOf()

}//end class
