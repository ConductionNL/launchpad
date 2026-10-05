<?php

/**
 * AnnouncementController
 *
 * Endpoints for announcements (engagement-announcements, REQ-ANN-001..005):
 * the reader's list, one announcement, authoring, publishing, the reach
 * count and "Preview as", likes, comments, category follows and the
 * editor-groups setting. Every endpoint passes the ADR-023 action check;
 * visibility per announcement is checked again by AnnouncementService, so
 * an id that does not target the caller reads as 404 (no IDOR).
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

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnnouncementService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Announcement endpoints.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) One method per route.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */
class AnnouncementController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request HTTP request.
	 * @param AnnouncementService $announcements Announcement service.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession $userSession Current user.
	 * @param IGroupManager $groupManager Admin check for the settings.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementService $announcements,
		private readonly ActionAuthService $actionAuth,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * Announcements the caller sees now; with scope "manage", every
	 * announcement including drafts (authors only).
	 *
	 * @param string|null $kind news or notice, or both when empty.
	 * @param string|null $scope "manage" for the authors' list.
	 *
	 * @return JSONResponse `{announcements: [...], canAuthor: bool, following: [...]}`.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function index(?string $kind = null, ?string $scope = null): JSONResponse {
		$action = 'announcement.read';
		if ($scope === 'manage') {
			$action = 'announcement.manage';
		}

		return $this->run(
			action: $action,
			work: function (string $userId) use ($kind, $scope): JSONResponse {
				$list = ($scope === 'manage')
					? $this->announcements->listForAuthor(userId: $userId)
					: $this->announcements->listVisible(userId: $userId, kind: ($kind === '' ? null : $kind));

				return new JSONResponse(
					data: [
						'announcements' => $list,
						'canAuthor'     => $this->announcements->canAuthor(userId: $userId),
						'following'     => $this->announcements->followedCategories(userId: $userId),
					]
				);
			}
		);
	}//end index()

	/**
	 * One announcement the caller may see.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse The announcement, or 404.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function show(string $uuid): JSONResponse {
		return $this->run(
			action: 'announcement.read',
			work: fn (string $userId): JSONResponse => new JSONResponse(data: $this->announcements->get(userId: $userId, uuid: $uuid))
		);
	}//end show()

	/**
	 * Create a draft (authors).
	 *
	 * @return JSONResponse The draft, 400 or 403.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function create(): JSONResponse {
		$data = $this->request->getParams();

		return $this->run(
			action: 'announcement.manage',
			work: fn (string $userId): JSONResponse => new JSONResponse(
				data: $this->announcements->create(userId: $userId, data: $data)->jsonSerialize(),
				statusCode: Http::STATUS_CREATED
			)
		);
	}//end create()

	/**
	 * Update an announcement (authors).
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse The announcement, 400, 403 or 404.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function update(string $uuid): JSONResponse {
		$data = $this->request->getParams();
		unset($data['uuid'], $data['_route']);

		return $this->run(
			action: 'announcement.manage',
			work: fn (string $userId): JSONResponse => new JSONResponse(
				data: $this->announcements->update(userId: $userId, uuid: $uuid, data: $data)->jsonSerialize()
			)
		);
	}//end update()

	/**
	 * Publish an announcement (authors). A notice without an end time is 400.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse The announcement, 400, 403 or 404.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function publish(string $uuid): JSONResponse {
		return $this->run(
			action: 'announcement.manage',
			work: fn (string $userId): JSONResponse => new JSONResponse(
				data: $this->announcements->publish(userId: $userId, uuid: $uuid)->jsonSerialize()
			)
		);
	}//end publish()

	/**
	 * Delete an announcement (authors).
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse 204, 403 or 404.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function destroy(string $uuid): JSONResponse {
		return $this->run(
			action: 'announcement.manage',
			work: function (string $userId) use ($uuid): JSONResponse {
				$this->announcements->delete(userId: $userId, uuid: $uuid);

				return new JSONResponse(data: [], statusCode: Http::STATUS_NO_CONTENT);
			}
		);
	}//end destroy()

	/**
	 * Reach count for target groups, and with a user id whether that person
	 * is reached ("Preview as").
	 *
	 * @param array|null $targetGroups Target group ids; empty means everyone.
	 * @param string|null $previewUserId Person to check, optional.
	 *
	 * @return JSONResponse `{reach: int, reachesPreviewUser: bool|null}`, 400 or 403.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function reach(?array $targetGroups = null, ?string $previewUserId = null): JSONResponse {
		return $this->run(
			action: 'announcement.manage',
			work: function (string $userId) use ($targetGroups, $previewUserId): JSONResponse {
				$groups  = ($targetGroups ?? []);
				$reaches = null;
				if ($previewUserId !== null && $previewUserId !== '') {
					$reaches = $this->announcements->reachesPerson(userId: $userId, groups: $groups, readerId: $previewUserId);
				}

				return new JSONResponse(
					data: [
						'reach'              => $this->announcements->reach(userId: $userId, groups: $groups),
						'reachesPreviewUser' => $reaches,
					]
				);
			}
		);
	}//end reach()

	/**
	 * Like or unlike an announcement the caller sees.
	 *
	 * @param string $uuid The announcement.
	 * @param boolean $liked True to like, false to take it back.
	 *
	 * @return JSONResponse The announcement with fresh counts, or 404.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function like(string $uuid, bool $liked = true): JSONResponse {
		return $this->run(
			action: 'announcement.comment',
			work: fn (string $userId): JSONResponse => new JSONResponse(
				data: $this->announcements->setLiked(userId: $userId, uuid: $uuid, liked: $liked)
			)
		);
	}//end like()

	/**
	 * The comments on an announcement the caller sees.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse `{comments: [...]}` or 404.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function comments(string $uuid): JSONResponse {
		return $this->run(
			action: 'announcement.read',
			work: fn (string $userId): JSONResponse => new JSONResponse(
				data: ['comments' => $this->announcements->listComments(userId: $userId, uuid: $uuid)]
			)
		);
	}//end comments()

	/**
	 * Comment on an announcement the caller sees and that allows comments.
	 *
	 * @param string $uuid The announcement.
	 * @param string $message The comment.
	 *
	 * @return JSONResponse The comment, 400, 403 or 404.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function comment(string $uuid, string $message = ''): JSONResponse {
		return $this->run(
			action: 'announcement.comment',
			work: fn (string $userId): JSONResponse => new JSONResponse(
				data: $this->announcements->addComment(userId: $userId, uuid: $uuid, message: $message),
				statusCode: Http::STATUS_CREATED
			)
		);
	}//end comment()

	/**
	 * Follow or unfollow a category.
	 *
	 * @param string $category The category.
	 * @param boolean $follow True to follow, false to stop.
	 *
	 * @return JSONResponse `{following: [...]}` or 400.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[NoAdminRequired]
	public function follow(string $category = '', bool $follow = true): JSONResponse {
		return $this->run(
			action: 'announcement.follow',
			work: fn (string $userId): JSONResponse => new JSONResponse(
				data: ['following' => $this->announcements->setFollowing(userId: $userId, category: $category, follow: $follow)]
			)
		);
	}//end follow()

	/**
	 * The editor groups (administrators).
	 *
	 * @return JSONResponse `{groups: [...]}` or 403.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getSettings(): JSONResponse {
		return $this->runAdmin(
			work: fn (): JSONResponse => new JSONResponse(data: ['groups' => $this->announcements->getEditorGroups()])
		);
	}//end getSettings()

	/**
	 * Replace the editor groups (administrators).
	 *
	 * @param array|null $groups Group ids.
	 *
	 * @return JSONResponse `{groups: [...]}`, 400 or 403.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function saveSettings(?array $groups = null): JSONResponse {
		return $this->runAdmin(
			work: fn (): JSONResponse => new JSONResponse(data: ['groups' => $this->announcements->saveEditorGroups(groups: ($groups ?? []))])
		);
	}//end saveSettings()

	/**
	 * Run an administrator-only endpoint behind `announcement.settings`.
	 *
	 * @param callable $work Produces the response.
	 *
	 * @return JSONResponse
	 */
	private function runAdmin(callable $work): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user !== null && $this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Admin required'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return $this->run(action: 'announcement.settings', work: fn (string $userId): JSONResponse => $work());
	}//end runAdmin()

	/**
	 * Check sign-in and the action, run the work and map errors to statuses.
	 *
	 * @param string $action ADR-023 action.
	 * @param callable $work Receives the caller's user id, returns the response.
	 *
	 * @return JSONResponse
	 */
	private function run(string $action, callable $work): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->actionAuth->requireAction($user, $action);

			return $work($user->getUID());
		} catch (OCSForbiddenException | ForbiddenException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_FORBIDDEN);
		} catch (DoesNotExistException) {
			return new JSONResponse(data: ['error' => 'Announcement not found'], statusCode: Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}
	}//end run()
}//end class
