<?php

/**
 * EmbedTokenApiController
 *
 * The admin API of embed tokens (REQ-EMB-001, REQ-EMB-008, REQ-EMB-009):
 * list, show, issue, update, revoke, un-revoke, re-issue, purge the
 * revocation cache, the aggregate usage report, and the telemetry switch.
 * Admin-only: the admin-setting attribute plus the request guard.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
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

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\Embed\EmbedTokenService;
use OCA\LaunchPad\Service\Embed\UsageTelemetryService;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use RuntimeException;

/**
 * Admin endpoints for embed tokens.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
 */
class EmbedTokenApiController extends Controller {

	/**
	 * The fields a create reads from the request.
	 *
	 * @var list<string>
	 */
	private const CREATE_FIELDS = ['name', 'description', 'subject', 'scope', 'hostOrigins', 'tenantThemeId', 'tokenExpiresAt', 'rateLimitPolicy'];

	/**
	 * The fields an update reads from the request.
	 *
	 * @var list<string>
	 */
	private const UPDATE_FIELDS = ['name', 'description', 'rateLimitPolicy'];

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request   The request.
	 * @param EmbedTokenService     $tokens    The token lifecycle.
	 * @param UsageTelemetryService $telemetry Usage reports and the telemetry switch.
	 * @param RequestGuard          $guard     The admin check.
	 */
	public function __construct(
		IRequest $request,
		private readonly EmbedTokenService $tokens,
		private readonly UsageTelemetryService $telemetry,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Every token's metadata.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		return $this->asAdmin(action: fn (string $admin): JSONResponse => new JSONResponse(data: ['results' => $this->tokens->listTokens()]));
	}//end index()

	/**
	 * One token's metadata, never its JWT or hash.
	 *
	 * @param string $id The token id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function show(string $id): JSONResponse {
		return $this->asAdmin(action: fn (string $admin): JSONResponse => self::found(data: $this->tokens->getToken(id: $id)));
	}//end show()

	/**
	 * Issue a token; the JWT is in this response and nowhere else.
	 *
	 * @return JSONResponse 201 with the JWT, 400 with a refusal.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function create(): JSONResponse {
		return $this->asAdmin(
			action: function (string $admin): JSONResponse {
				$result = $this->tokens->createToken(input: $this->input(fields: self::CREATE_FIELDS), userId: $admin);

				return self::outcome(data: $result, success: Http::STATUS_CREATED);
			}
		);
	}//end create()

	/**
	 * Update a token's name, description or rate limit.
	 *
	 * @param string $id The token id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(string $id): JSONResponse {
		return $this->asAdmin(
			action: fn (string $admin): JSONResponse => self::found(
				data: $this->tokens->updateToken(id: $id, input: $this->input(fields: self::UPDATE_FIELDS))
			)
		);
	}//end update()

	/**
	 * Revoke a token.
	 *
	 * @param string      $id     The token id.
	 * @param string      $reason The revocation reason.
	 * @param string|null $notes  Optional notes.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function revoke(string $id, string $reason='', ?string $notes=null): JSONResponse {
		return $this->asAdmin(
			action: fn (string $admin): JSONResponse => self::found(
				data: $this->tokens->revokeToken(id: $id, reason: $reason, notes: $notes, userId: $admin)
			)
		);
	}//end revoke()

	/**
	 * Clear a token's revocation.
	 *
	 * @param string $id The token id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function unrevoke(string $id): JSONResponse {
		return $this->asAdmin(action: fn (string $admin): JSONResponse => self::found(data: $this->tokens->unrevokeToken(id: $id)));
	}//end unrevoke()

	/**
	 * Re-issue: revoke the token and issue a like one; the new JWT is shown once.
	 *
	 * @param string $id The token id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function reissue(string $id): JSONResponse {
		return $this->asAdmin(
			action: function (string $admin) use ($id): JSONResponse {
				$result = $this->tokens->reissueToken(id: $id, userId: $admin);
				if ($result === null) {
					return self::notFound();
				}

				return new JSONResponse(data: $result, statusCode: Http::STATUS_CREATED);
			}
		);
	}//end reissue()

	/**
	 * Make every render worker re-read the token now.
	 *
	 * @param string $id The token id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function purgeCache(string $id): JSONResponse {
		return $this->asAdmin(
			action: function (string $admin) use ($id): JSONResponse {
				$at = $this->tokens->purgeCache(id: $id);
				if ($at === null) {
					return self::notFound();
				}

				return new JSONResponse(
					data: ['message' => 'Cache purged. All render workers will immediately reject this token.', 'purgedAt' => $at]
				);
			}
		);
	}//end purgeCache()

	/**
	 * A token's usage over the last seven days, in aggregate.
	 *
	 * @param string $id The token id.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-29
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function usage(string $id): JSONResponse {
		return $this->asAdmin(
			action: function (string $admin) use ($id): JSONResponse {
				$token = $this->tokens->getToken(id: $id);
				if ($token === null) {
					return self::notFound();
				}

				return new JSONResponse(data: $this->telemetry->report(tokenId: $id, revokedAt: $token['revokedAt'] ?? null));
			}
		);
	}//end usage()

	/**
	 * Whether embed usage telemetry is collected.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function telemetry(): JSONResponse {
		return $this->asAdmin(action: fn (string $admin): JSONResponse => new JSONResponse(data: ['enabled' => $this->telemetry->isEnabled()]));
	}//end telemetry()

	/**
	 * Turn embed usage telemetry on or off.
	 *
	 * @param bool $enabled Whether to collect.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function setTelemetry(bool $enabled): JSONResponse {
		return $this->asAdmin(
			action: function (string $admin) use ($enabled): JSONResponse {
				$this->telemetry->setEnabled(enabled: $enabled, userId: $admin);

				return new JSONResponse(data: ['enabled' => $this->telemetry->isEnabled()]);
			}
		);
	}//end setTelemetry()

	/**
	 * Run an action as the signed-in admin; 401/403 otherwise, 503 without OpenRegister.
	 *
	 * @param callable $action fn(string $admin): JSONResponse.
	 *
	 * @return JSONResponse
	 */
	private function asAdmin(callable $action): JSONResponse {
		$admin = $this->guard->adminId();
		if ($admin instanceof JSONResponse) {
			return $admin;
		}

		try {
			return $action($admin);
		} catch (RuntimeException $failure) {
			return new JSONResponse(data: ['error' => 'unavailable', 'message' => $failure->getMessage()], statusCode: Http::STATUS_SERVICE_UNAVAILABLE);
		}
	}//end asAdmin()

	/**
	 * The named request fields that are present.
	 *
	 * @param list<string> $fields The fields.
	 *
	 * @return array<string, mixed>
	 */
	private function input(array $fields): array {
		$params = $this->request->getParams();
		$input  = [];
		foreach ($fields as $field) {
			if (array_key_exists($field, $params) === true) {
				$input[$field] = $params[$field];
			}
		}

		return $input;
	}//end input()

	/**
	 * 404 for null, 400 for a refusal, 200 otherwise.
	 *
	 * @param array<string, mixed>|null $data The result.
	 *
	 * @return JSONResponse
	 */
	private static function found(?array $data): JSONResponse {
		if ($data === null) {
			return self::notFound();
		}

		return self::outcome(data: $data, success: Http::STATUS_OK);
	}//end found()

	/**
	 * 400 for a refusal, the success status otherwise.
	 *
	 * @param array<string, mixed> $data    The result.
	 * @param int                  $success The success status.
	 *
	 * @return JSONResponse
	 */
	private static function outcome(array $data, int $success): JSONResponse {
		if (($data['status'] ?? null) === 'error') {
			return new JSONResponse(data: $data, statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: $data, statusCode: $success);
	}//end outcome()

	/**
	 * The 404 of an unknown token.
	 *
	 * @return JSONResponse
	 */
	private static function notFound(): JSONResponse {
		return new JSONResponse(data: ['error' => 'not_found', 'message' => 'There is no such embed token.'], statusCode: Http::STATUS_NOT_FOUND);
	}//end notFound()
}//end class
