<?php

/**
 * DashboardReactionApiController
 *
 * Controller for dashboard emoji reaction endpoints — REQ-RXN-001..009.
 * Owns the four routes:
 *   - GET    /api/dashboards/{uuid}/reactions
 *   - POST   /api/dashboards/{uuid}/reactions          (idempotent)
 *   - DELETE /api/dashboards/{uuid}/reactions/{emoji}  (idempotent)
 *   - GET    /api/dashboards/{uuid}/reactions/{emoji}/users
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Closure;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\PermissionDeniedException;
use OCA\LaunchPad\Service\ReactionsDisabledException;
use OCA\LaunchPad\Service\ReactionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use Throwable;

/**
 * Dashboard reaction endpoints.
 *
 * Permission gating is runtime (calls into PermissionService through
 * ReactionService) so every method carries `#[NoAdminRequired]` —
 * non-admins can react if and only if they can VIEW the dashboard.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Reaction controller spans
 *                                                  the reaction service,
 *                                                  action-auth service,
 *                                                  user session, logger,
 *                                                  and four exception types
 *                                                  across four routes.
 */
class DashboardReactionApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor
	 *
	 * @param IRequest      $request The request.
	 * @param ReactionTools $reactions The reaction service and logger.
	 * @param ActionCaller  $caller The session and the action check.
	 * @param string|null   $userId The acting user ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly ReactionTools $reactions,
		private readonly ActionCaller $caller,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET /api/dashboards/{uuid}/reactions — return the
	 * `{counts, mine, enabled}` summary. REQ-RXN-003.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return JSONResponse The summary.
	 *
	 * @spec openspec/specs/dashboard-reactions/spec.md
	 */
	#[NoAdminRequired]
	public function getReactions(string $uuid): JSONResponse {
		$refusal = $this->refuseCaller(
			check: fn (IUser $user) => $this->caller->actionAuth->requireAction($user, 'dashboard-reaction.get-reactions')
		);
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$summary = $this->reactions->reactionService->getReactionsSummary(
				dashboardUuid: $uuid,
				userId: $this->userId
			);
		} catch (Throwable $e) {
			return $this->reactionFailure(exception: $e, operation: 'getReactions');
		}

		return ResponseHelper::success(data: $summary);
	}//end getReactions()

	/**
	 * POST /api/dashboards/{uuid}/reactions — add the calling user's
	 * reaction. Idempotent (REQ-RXN-001 scenario "User re-posts the
	 * same emoji").
	 *
	 * @param string $uuid The dashboard UUID.
	 * @param string $emoji The emoji to add (request body field).
	 *
	 * @return JSONResponse The updated summary.
	 *
	 * @spec openspec/specs/dashboard-reactions/spec.md
	 */
	#[NoAdminRequired]
	public function addReaction(string $uuid, string $emoji = ''): JSONResponse {
		$refusal = $this->refuseCaller(
			check: fn (IUser $user) => $this->caller->actionAuth->requireAction($user, 'dashboard-reaction.add-reaction')
		);
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$summary = $this->reactions->reactionService->addReaction(
				dashboardUuid: $uuid,
				userId: $this->userId,
				emoji: $emoji
			);
		} catch (Throwable $e) {
			return $this->addFailure(exception: $e);
		}

		return ResponseHelper::success(data: $summary);
	}//end addReaction()

	/**
	 * DELETE /api/dashboards/{uuid}/reactions/{emoji} — remove the
	 * calling user's reaction. Idempotent (REQ-RXN-002).
	 *
	 * @param string $uuid The dashboard UUID.
	 * @param string $emoji The emoji to remove.
	 *
	 * @return JSONResponse Empty 204 response.
	 *
	 * @spec openspec/specs/dashboard-reactions/spec.md
	 */
	#[NoAdminRequired]
	public function removeReaction(string $uuid, string $emoji): JSONResponse {
		$refusal = $this->refuseCaller(
			check: fn (IUser $user) => $this->caller->actionAuth->requireAction($user, 'dashboard-reaction.remove-reaction')
		);
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$this->reactions->reactionService->removeReaction(
				dashboardUuid: $uuid,
				userId: $this->userId,
				emoji: $emoji
			);
		} catch (Throwable $e) {
			return $this->reactionFailure(exception: $e, operation: 'removeReaction');
		}

		// 204 No Content — JSONResponse with empty body and explicit
		// status (the framework still emits headers/body shape, but
		// the contract is "204 always" per REQ-RXN-002).
		return new JSONResponse(
			data: [],
			statusCode: Http::STATUS_NO_CONTENT
		);
	}//end removeReaction()

	/**
	 * GET /api/dashboards/{uuid}/reactions/{emoji}/users — return the
	 * paginated list of reactors. REQ-RXN-004.
	 *
	 * @param string $uuid The dashboard UUID.
	 * @param string $emoji The emoji.
	 * @param string|null $cursor Optional opaque cursor (offset).
	 *
	 * @return JSONResponse The reactors page.
	 *
	 * @spec openspec/specs/dashboard-reactions/spec.md
	 */
	#[NoAdminRequired]
	public function getReactorsByEmoji(
		string $uuid,
		string $emoji,
		?string $cursor = null,
	): JSONResponse {
		$refusal = $this->refuseCaller(
			check: fn (IUser $user) => $this->caller->actionAuth->requireAction($user, 'dashboard-reaction.get-reactors-by-emoji')
		);
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$page = $this->reactions->reactionService->getReactorsByEmoji(
				dashboardUuid: $uuid,
				emoji: $emoji,
				userId: $this->userId,
				cursor: $cursor
			);
		} catch (Throwable $e) {
			return $this->reactionFailure(exception: $e, operation: 'getReactorsByEmoji');
		}

		return ResponseHelper::success(data: $page);
	}//end getReactorsByEmoji()

	/**
	 * The 401 for a request without a session user; otherwise the action
	 * check, which throws when the caller's groups may not perform it.
	 *
	 * @param Closure(IUser): void $check Runs the action check for the session user.
	 *
	 * @return JSONResponse|null The 401, or null when the caller may go on.
	 */
	private function refuseCaller(Closure $check): JSONResponse|null {
		$user = $this->caller->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		$check($user);
		return null;
	}//end refuseCaller()

	/**
	 * Answer a failed reaction operation: 404 for an unknown dashboard,
	 * 403 when the caller may not see it, otherwise a logged 500.
	 *
	 * @param Throwable $exception The failure.
	 * @param string    $operation The operation name for the log line.
	 *
	 * @return JSONResponse The error response.
	 */
	private function reactionFailure(Throwable $exception, string $operation): JSONResponse {
		if ($exception instanceof DoesNotExistException) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		}

		if ($exception instanceof PermissionDeniedException) {
			return ResponseHelper::forbidden(message: $exception->getMessage());
		}

		$this->reactions->logger->error(
			message: $operation . ' failed: ' . $exception->getMessage(),
			context: ['exception' => $exception]
		);
		return $this->failure(message: 'Operation failed', statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
	}//end reactionFailure()

	/**
	 * Answer a failed add: the shared answers for an unknown or hidden
	 * dashboard, 403 when reactions are off, 400 for an invalid emoji,
	 * otherwise a logged 500.
	 *
	 * @param Throwable $exception The failure.
	 *
	 * @return JSONResponse The error response.
	 */
	private function addFailure(Throwable $exception): JSONResponse {
		if ($exception instanceof DoesNotExistException || $exception instanceof PermissionDeniedException) {
			return $this->reactionFailure(exception: $exception, operation: 'addReaction');
		}

		if ($exception instanceof ReactionsDisabledException) {
			return ResponseHelper::forbidden(message: $exception->getMessage());
		}

		if ($exception instanceof InvalidArgumentException) {
			return $this->failure(message: $exception->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}

		return $this->reactionFailure(exception: $exception, operation: 'addReaction');
	}//end addFailure()
}//end class
