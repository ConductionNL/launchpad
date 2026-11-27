<?php

/**
 * AnnouncementController
 *
 * The announcements API (engagement-announcements task 4). Every method
 * checks its ADR-023 action first, then {@see AnnouncementService} decides per
 * id: an announcement a user may not see answers 404, never 403, so an id
 * reveals nothing.
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
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementCommentServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementFollowServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementReceiptReport;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * List, read, write, publish and follow announcements.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class AnnouncementController extends Controller {
	use AnswersAnnouncementRequests;


	/**
	 * The fields an author may send.
	 */
	private const FIELDS = [
		'kind',
		'title',
		'body',
		'category',
		'level',
		'dismissible',
		'targetGroups',
		'publishAt',
		'expiresAt',
		'allowComments',
		'requiresConfirmation',
		'confirmBefore',
	];

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request       The request.
	 * @param AnnouncementServiceInterface $announcements The announcements.
	 * @param RequestGuard        $guard         The session user and the action check.
	 * @param AnnouncementFollowServiceInterface $follows The followed categories.
	 * @param AnnouncementCommentServiceInterface|null $reactions Likes and comments through Nextcloud's comments service.
	 * @param AnnouncementReceiptReport|null $receipts The seen and confirmed counts (REQ-ANN-008, REQ-ANN-009).
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementServiceInterface $announcements,
		private readonly RequestGuard $guard,
		private readonly AnnouncementFollowServiceInterface $follows,
		private readonly ?AnnouncementCommentServiceInterface $reactions = null,
		private readonly ?AnnouncementReceiptReport $receipts = null,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/announcements`: what the user sees now and the categories they follow; drafts are managed on the Announcements tab (REQ-ANN-010).
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(run: function (string $userId): JSONResponse {
			$visible = $this->announcements->visibleTo(userId: $userId);
			return new JSONResponse(
				[
					'announcements' => array_map(static fn (Announcement $item): array => $item->jsonSerialize(), $visible),
					'followedCategories' => $this->follows->followedCategories(userId: $userId),
					'canAuthor' => $this->announcements->isAuthor(userId: $userId),
					'reactions' => (object) ($this->reactions?->countsFor(userId: $userId, announcements: $visible) ?? []),
				]
			);
		});
	}//end index()

	/**
	 * `GET /api/announcements/manage`: every announcement for the
	 * Announcements tab, news and notices, active, scheduled, ended and
	 * drafts (REQ-ANN-008). Nextcloud checks the announcements setting before
	 * this runs (REQ-ADM-034).
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function manage(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: function (string $userId): JSONResponse {
			$all    = $this->announcements->allFor(userId: $userId);
			$counts = $this->receipts?->countsFor(
				uuids: array_map(static fn (Announcement $item): string => (string) $item->getUuid(), $all)
			) ?? [];

			return new JSONResponse(
				[
					'announcements' => array_map(
						static fn (Announcement $item): array => $item->jsonSerialize() + [
							'seenCount'      => $counts[(string) $item->getUuid()]['seen'] ?? 0,
							'confirmedCount' => $counts[(string) $item->getUuid()]['confirmed'] ?? 0,
						],
						$all
					),
				]
			);
		});
	}//end manage()

	/**
	 * `GET /api/announcements/{uuid}`: one announcement; 404 when the user may not see it.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	#[NoAdminRequired]
	public function show(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(run: fn (string $userId): JSONResponse => new JSONResponse(
			$this->announcements->findFor(userId: $userId, uuid: $uuid)->jsonSerialize()
		));
	}//end show()

	/**
	 * `POST /api/announcements`: write a draft; 403 for someone who may not author.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function create(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: fn (string $userId): JSONResponse => new JSONResponse(
			$this->announcements->create(userId: $userId, input: $this->input())->jsonSerialize(),
			Http::STATUS_CREATED
		));
	}//end create()

	/**
	 * `PUT /api/announcements/{uuid}`: change an announcement.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function update(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: fn (string $userId): JSONResponse => new JSONResponse(
			$this->announcements->update(userId: $userId, uuid: $uuid, input: $this->input())->jsonSerialize()
		));
	}//end update()

	/**
	 * `POST /api/announcements/{uuid}/publish`: publish after the preview; a notice without an end time is refused.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function publish(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: fn (string $userId): JSONResponse => new JSONResponse(
			$this->announcements->publish(userId: $userId, uuid: $uuid)->jsonSerialize()
		));
	}//end publish()

	/**
	 * `DELETE /api/announcements/{uuid}`: delete an announcement.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function destroy(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: function (string $userId) use ($uuid): JSONResponse {
			$this->announcements->delete(userId: $userId, uuid: $uuid);
			$this->reactions?->purge(uuid: $uuid);
			$this->receipts?->purge(uuid: $uuid);
			return new JSONResponse(['deleted' => $uuid]);
		});
	}//end destroy()

	/**
	 * `GET /api/announcements/reach`: about how many people the target groups reach, for the preview.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
	 */
	#[AuthorizedAdminSetting(settings: LaunchPadAnnouncementsAdmin::class)]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function reach(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: function (string $userId): JSONResponse {
			if ($this->announcements->isAuthor(userId: $userId) === false) {
				throw new ForbiddenException(message: 'Only announcement editors may preview the reach');
			}

			$groups = $this->request->getParam(key: 'groups', default: []);
			if (is_array($groups) === false) {
				$groups = [];
			}

			$groups = array_values(array_filter($groups, 'is_string'));
			$answer = ['reach' => $this->announcements->reach(groups: $groups)];
			// "Preview as" (D4): whether the targeting reaches one chosen person.
			$previewAs = trim((string) $this->request->getParam(key: 'user', default: ''));
			if ($previewAs !== '') {
				$answer['reachesUser'] = $this->announcements->reachesUser(groups: $groups, userId: $previewAs);
			}

			return new JSONResponse($answer);
		});
	}//end reach()

	/**
	 * `POST /api/announcements/follows`: follow a category.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	#[NoAdminRequired]
	public function follow(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.follow') ?? $this->asUser(run: fn (string $userId): JSONResponse => new JSONResponse(
			['followedCategories' => $this->follows->follow(userId: $userId, category: (string) $this->request->getParam(key: 'category', default: ''))]
		));
	}//end follow()

	/**
	 * `DELETE /api/announcements/follows/{category}`: stop following a category.
	 *
	 * @param string $category The category.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	#[NoAdminRequired]
	public function unfollow(string $category): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.follow') ?? $this->asUser(run: fn (string $userId): JSONResponse => new JSONResponse(
			['followedCategories' => $this->follows->unfollow(userId: $userId, category: $category)]
		));
	}//end unfollow()

	/**
	 * `GET /api/announcements/{uuid}/comments`: the comments on an announcement, oldest first.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	#[NoAdminRequired]
	public function comments(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->withReactions(
			run: fn (string $userId, AnnouncementCommentServiceInterface $reactions): JSONResponse => new JSONResponse(
				['comments' => $reactions->commentsOn(userId: $userId, uuid: $uuid)]
			)
		);
	}//end comments()

	/**
	 * `POST /api/announcements/{uuid}/comments`: comment on an announcement that allows comments.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	#[NoAdminRequired]
	public function comment(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.comment') ?? $this->withReactions(
			run: fn (string $userId, AnnouncementCommentServiceInterface $reactions): JSONResponse => new JSONResponse(
				$reactions->comment(userId: $userId, uuid: $uuid, message: (string) $this->request->getParam(key: 'message', default: '')),
				Http::STATUS_CREATED
			)
		);
	}//end comment()

	/**
	 * `POST /api/announcements/{uuid}/like`: like an announcement.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	#[NoAdminRequired]
	public function like(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.comment') ?? $this->withReactions(
			run: fn (string $userId, AnnouncementCommentServiceInterface $reactions): JSONResponse => new JSONResponse(
				$reactions->like(userId: $userId, uuid: $uuid)
			)
		);
	}//end like()

	/**
	 * `DELETE /api/announcements/{uuid}/like`: take back a like.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	#[NoAdminRequired]
	public function unlike(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.comment') ?? $this->withReactions(
			run: fn (string $userId, AnnouncementCommentServiceInterface $reactions): JSONResponse => new JSONResponse(
				$reactions->unlike(userId: $userId, uuid: $uuid)
			)
		);
	}//end unlike()

	/**
	 * Run a likes-and-comments call as the session user, or 503 when the comments service is not wired.
	 *
	 * @param callable $run Receives the user id and the comments service.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	private function withReactions(callable $run): JSONResponse {
		$reactions = $this->reactions;
		if ($reactions === null) {
			return new JSONResponse(['error' => 'Likes and comments are not available'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		return $this->asUser(run: fn (string $userId): JSONResponse => $run($userId, $reactions));
	}//end withReactions()
	/**
	 * The author's fields from the request, only those that were sent.
	 *
	 * @return array<string, mixed>
	 */
	private function input(): array {
		$params = $this->request->getParams();
		$input = [];
		foreach (self::FIELDS as $field) {
			if (array_key_exists($field, $params) === true) {
				$input[$field] = $params[$field];
			}
		}

		return $input;
	}//end input()
}//end class
