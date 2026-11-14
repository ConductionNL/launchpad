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

use Closure;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AnnouncementFollowService;
use OCA\LaunchPad\Service\AnnouncementService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * List, read, write, publish and follow announcements.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class AnnouncementController extends Controller {

	/**
	 * The fields an author may send.
	 */
	private const FIELDS = ['kind', 'title', 'body', 'category', 'level', 'dismissible', 'targetGroups', 'publishAt', 'expiresAt', 'allowComments'];

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request       The request.
	 * @param AnnouncementService $announcements The announcements.
	 * @param RequestGuard        $guard         The session user and the action check.
	 * @param AnnouncementFollowService $follows The followed categories.
	 */
	public function __construct(
		IRequest $request,
		private readonly AnnouncementService $announcements,
		private readonly RequestGuard $guard,
		private readonly AnnouncementFollowService $follows,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/announcements`: what the user sees now, their drafts when they author, and the categories they follow.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.read') ?? $this->asUser(run: fn (string $userId): JSONResponse => new JSONResponse(
			[
				'announcements' => array_map(static fn (Announcement $item): array => $item->jsonSerialize(), $this->announcements->visibleTo(userId: $userId)),
				'drafts' => array_map(static fn (Announcement $item): array => $item->jsonSerialize(), $this->announcements->draftsFor(userId: $userId)),
				'followedCategories' => $this->follows->followedCategories(userId: $userId),
				'canAuthor' => $this->announcements->isAuthor(userId: $userId),
			]
		));
	}//end index()

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
	#[NoAdminRequired]
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
	#[NoAdminRequired]
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
	#[NoAdminRequired]
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
	#[NoAdminRequired]
	public function destroy(string $uuid): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: function (string $userId) use ($uuid): JSONResponse {
			$this->announcements->delete(userId: $userId, uuid: $uuid);
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
	#[NoAdminRequired]
	public function reach(): JSONResponse {
		return $this->guard->actionRefusal(action: 'announcement.manage') ?? $this->asUser(run: function (string $userId): JSONResponse {
			if ($this->announcements->isAuthor(userId: $userId) === false) {
				throw new ForbiddenException(message: 'Only announcement editors may preview the reach');
			}

			$groups = $this->request->getParam(key: 'groups', default: []);
			if (is_array($groups) === false) {
				$groups = [];
			}

			return new JSONResponse(['reach' => $this->announcements->reach(groups: array_values(array_filter($groups, 'is_string')))]);
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
	 * Run an endpoint as the session user, mapping refusals to their status. The
	 * caller has checked its ADR-023 action first.
	 *
	 * @param Closure $run `(string $userId): JSONResponse`.
	 *
	 * @return JSONResponse
	 */
	private function asUser(Closure $run): JSONResponse {
		$user = $this->guard->sessionUser();
		if ($user instanceof JSONResponse) {
			return $user;
		}

		try {
			return $run($user->getUID());
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Announcement not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['status' => 'error', 'error' => 'invalid_argument', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end asUser()

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
