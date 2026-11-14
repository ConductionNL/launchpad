<?php

/**
 * CatalogueService
 *
 * The app catalogue (launcher-app-catalogue, REQ-ACAT-001..004): the
 * applications an administrator approved, filtered by group for each user,
 * and the requests users send for an application that is missing. A request
 * notifies the administrators and the optional catalogue manager group; a
 * decision notifies the requester.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use DateTime;
use DomainException;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\AppRequest;
use OCA\LaunchPad\Db\AppRequestMapper;
use OCA\LaunchPad\Db\CatalogueApp;
use OCA\LaunchPad\Db\CatalogueAppMapper;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * App catalogue service.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) One service for the catalogue and its requests, as the design keeps them together.
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-3
 */
class CatalogueService {

	/**
	 * Most open requests one user may have (REQ-ACAT-003).
	 *
	 * @var int
	 */
	public const MAX_OPEN_REQUESTS = 10;

	/**
	 * Admin setting naming the group whose members also hear about requests.
	 *
	 * @var string
	 */
	public const MANAGER_GROUP_SETTING = 'catalogue_manager_group';

	/**
	 * Error code of a refused request when the user has too many open ones.
	 *
	 * @var string
	 */
	public const ERROR_REQUEST_LIMIT = 'request_limit';

	/**
	 * Error code of a decision on a request that is no longer open.
	 *
	 * @var string
	 */
	public const ERROR_NOT_OPEN = 'request_not_open';

	/**
	 * The entries a demo install starts with (design, "Seed data").
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public const DEMO_ENTRIES = [
		[
			'name' => 'Zaaksysteem',
			'description' => 'Cases and permits.',
			'icon' => 'icon-category-organization',
			'linkType' => 'url',
			'linkValue' => 'https://zaken.example.nl',
		],
		[
			'name' => 'Personeelsportaal',
			'description' => 'Leave, payslips and personal details.',
			'icon' => 'icon-user',
			'linkType' => 'url',
			'linkValue' => 'https://hr.example.nl',
		],
		[
			'name' => 'Bestanden',
			'description' => 'Your files and shared folders.',
			'icon' => 'icon-files-dark',
			'linkType' => 'app',
			'linkValue' => 'files',
		],
		[
			'name' => 'Agenda',
			'description' => 'Your calendar and meetings.',
			'icon' => 'icon-calendar-dark',
			'linkType' => 'app',
			'linkValue' => 'calendar',
		],
	];

	/**
	 * Constructor.
	 *
	 * @param CatalogueAppMapper   $appMapper           The approved applications.
	 * @param AppRequestMapper     $requestMapper       The requests.
	 * @param AdminTemplateService $groups              The caller's groups (the one place that reads them).
	 * @param IGroupManager        $groupManager        The administrators and the manager group.
	 * @param IUserManager         $userManager         The requester's display name.
	 * @param AdminSettingMapper   $settingMapper       The catalogue manager group setting.
	 * @param INotificationManager $notificationManager Sends the notifications.
	 * @param LoggerInterface      $logger              Logs a notification that could not be sent.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-3
	 */
	public function __construct(
		private readonly CatalogueAppMapper $appMapper,
		private readonly AppRequestMapper $requestMapper,
		private readonly AdminTemplateService $groups,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly AdminSettingMapper $settingMapper,
		private readonly INotificationManager $notificationManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The entries a user may see: those for everyone and those for one of
	 * the user's groups (REQ-ACAT-002, design D2).
	 *
	 * @param string $userId The user.
	 *
	 * @return array<int, CatalogueApp>
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-add-catalogue-apps-as-tiles-from-the-add-flow-req-acat-002
	 */
	public function listFor(string $userId): array {
		$userGroups = $this->groups->getUserGroupIdsFor(userId: $userId);

		return array_values(
			array: array_filter(
				array: $this->appMapper->findAllOrdered(),
				callback: static function (CatalogueApp $app) use ($userGroups): bool {
					$targets = $app->getTargetGroupList();
					return $targets === [] || array_intersect($targets, $userGroups) !== [];
				}
			)
		);
	}//end listFor()

	/**
	 * Every entry, for the administrators.
	 *
	 * @return array<int, CatalogueApp>
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	public function listAll(): array {
		return $this->appMapper->findAllOrdered();
	}//end listAll()

	/**
	 * Add an entry.
	 *
	 * @param array<string, mixed> $input The entry's fields.
	 *
	 * @return CatalogueApp The stored entry.
	 *
	 * @throws InvalidArgumentException When a field is missing or invalid.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	public function create(array $input): CatalogueApp {
		$now = self::now();
		$app = new CatalogueApp();
		// Entity setters take their argument positionally.
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$app->setUuid(UuidV4::generate());
		$app->setCreatedAt($now);
		$app->setUpdatedAt($now);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->apply(app: $app, input: $input);

		return $this->appMapper->insert(entity: $app);
	}//end create()

	/**
	 * Change an entry. Tiles already placed from it keep what they had.
	 *
	 * @param string               $uuid  The entry.
	 * @param array<string, mixed> $input The entry's fields.
	 *
	 * @return CatalogueApp The stored entry.
	 *
	 * @throws InvalidArgumentException When a field is missing or invalid.
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When there is no such entry.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	public function update(string $uuid, array $input): CatalogueApp {
		$app = $this->appMapper->findByUuid(uuid: $uuid);
		$this->apply(app: $app, input: $input);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$app->setUpdatedAt(self::now());

		return $this->appMapper->update(entity: $app);
	}//end update()

	/**
	 * Remove an entry. Tiles already placed from it stay.
	 *
	 * @param string $uuid The entry.
	 *
	 * @return void
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When there is no such entry.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	public function delete(string $uuid): void {
		$this->appMapper->delete(entity: $this->appMapper->findByUuid(uuid: $uuid));
	}//end delete()

	/**
	 * Store a request for a missing application and tell the administrators
	 * and the catalogue manager group (REQ-ACAT-003, design D3).
	 *
	 * @param string $userId  The user asking.
	 * @param string $appName The application asked for.
	 * @param string $purpose What the user needs it for.
	 *
	 * @return AppRequest The stored request.
	 *
	 * @throws InvalidArgumentException When the name is empty or too long.
	 * @throws DomainException          When the user already has the most open requests allowed (code `request_limit`).
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
	 */
	public function request(string $userId, string $appName, string $purpose): AppRequest {
		$appName = trim(string: $appName);
		if ($appName === '' || mb_strlen(string: $appName) > 255) {
			throw new InvalidArgumentException(message: 'Give the name of the app, at most 255 characters.');
		}

		if ($this->requestMapper->countOpenBy(userId: $userId) >= self::MAX_OPEN_REQUESTS) {
			throw new DomainException(message: self::ERROR_REQUEST_LIMIT);
		}

		$request = new AppRequest();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$request->setUuid(UuidV4::generate());
		$request->setRequestedBy($userId);
		$request->setAppName($appName);
		$request->setPurpose(trim(string: $purpose));
		$request->setStatus('open');
		$request->setCreatedAt(self::now());
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$stored = $this->requestMapper->insert(entity: $request);

		$requester = ($this->userManager->getDisplayName(uid: $userId) ?? $userId);
		foreach ($this->requestRecipients() as $recipient) {
			$this->notify(
				userId: $recipient,
				subject: 'app_requested',
				params: [$requester, $appName],
				requestUuid: $stored->getUuid()
			);
		}

		return $stored;
	}//end request()

	/**
	 * The open requests, for the administrators.
	 *
	 * @return array<int, AppRequest>
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
	 */
	public function openRequests(): array {
		return $this->requestMapper->findOpen();
	}//end openRequests()

	/**
	 * Approve a request: add the catalogue entry the administrator completed
	 * (prefilled with the requested name), link the request to it and tell
	 * the requester.
	 *
	 * @param string               $requestUuid The request.
	 * @param string               $adminId     The administrator.
	 * @param array<string, mixed> $entry       The new entry's fields; the name defaults to the requested one.
	 *
	 * @return array{request: AppRequest, app: CatalogueApp}
	 *
	 * @throws InvalidArgumentException When the entry is invalid.
	 * @throws DomainException          When the request is no longer open (code `request_not_open`).
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When there is no such request.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
	 */
	public function approve(string $requestUuid, string $adminId, array $entry): array {
		$request = $this->openRequest(uuid: $requestUuid);
		if (trim(string: (string)($entry['name'] ?? '')) === '') {
			$entry['name'] = $request->getAppName();
		}

		$app = $this->create(input: $entry);
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$request->setStatus('approved');
		$request->setCatalogueAppId($app->getId());
		$request->setDecidedBy($adminId);
		$request->setDecidedAt(self::now());
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$request = $this->requestMapper->update(entity: $request);

		$this->notify(
			userId: $request->getRequestedBy(),
			subject: 'app_request_decided',
			params: [$app->getName(), 'approved', ''],
			requestUuid: $request->getUuid()
		);

		return ['request' => $request, 'app' => $app];
	}//end approve()

	/**
	 * Decline a request with a note and tell the requester why.
	 *
	 * @param string $requestUuid The request.
	 * @param string $adminId     The administrator.
	 * @param string $note        Why; required.
	 *
	 * @return AppRequest The stored request.
	 *
	 * @throws InvalidArgumentException When the note is empty.
	 * @throws DomainException          When the request is no longer open (code `request_not_open`).
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When there is no such request.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
	 */
	public function decline(string $requestUuid, string $adminId, string $note): AppRequest {
		$note = trim(string: $note);
		if ($note === '') {
			throw new InvalidArgumentException(message: 'Say why the request is declined.');
		}

		$request = $this->openRequest(uuid: $requestUuid);
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$request->setStatus('declined');
		$request->setDecisionNote($note);
		$request->setDecidedBy($adminId);
		$request->setDecidedAt(self::now());
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$request = $this->requestMapper->update(entity: $request);

		$this->notify(
			userId: $request->getRequestedBy(),
			subject: 'app_request_decided',
			params: [$request->getAppName(), 'declined', $note],
			requestUuid: $request->getUuid()
		);

		return $request;
	}//end decline()

	/**
	 * The group whose members also hear about requests, or ''.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-5
	 */
	public function getManagerGroup(): string {
		return (string)($this->settingMapper->getValue(key: self::MANAGER_GROUP_SETTING, default: '') ?? '');
	}//end getManagerGroup()

	/**
	 * Set the group whose members also hear about requests; '' for none.
	 *
	 * @param string $groupId The group id.
	 *
	 * @return string The stored group id.
	 *
	 * @throws InvalidArgumentException When the group does not exist.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-5
	 */
	public function setManagerGroup(string $groupId): string {
		$groupId = trim(string: $groupId);
		if ($groupId !== '' && $this->groupManager->groupExists(gid: $groupId) === false) {
			throw new InvalidArgumentException(message: 'There is no such group.');
		}

		$this->settingMapper->setSetting(key: self::MANAGER_GROUP_SETTING, value: $groupId);

		return $groupId;
	}//end setManagerGroup()

	/**
	 * Give an empty catalogue the four demo entries (design, "Seed data").
	 * A catalogue that already holds entries is left alone.
	 *
	 * @return int How many entries were added.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-9
	 */
	public function seedDemoEntries(): int {
		if ($this->appMapper->findAllOrdered() !== []) {
			return 0;
		}

		foreach (self::DEMO_ENTRIES as $position => $entry) {
			$this->create(input: [...$entry, 'iconType' => 'class', 'sortOrder' => $position]);
		}

		return count(self::DEMO_ENTRIES);
	}//end seedDemoEntries()

	/**
	 * Validate the input and write it onto an entry.
	 *
	 * @param CatalogueApp         $app   The entry.
	 * @param array<string, mixed> $input The fields.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a field is missing or invalid.
	 */
	private function apply(CatalogueApp $app, array $input): void {
		$name = trim(string: (string)($input['name'] ?? ''));
		if ($name === '' || mb_strlen(string: $name) > 255) {
			throw new InvalidArgumentException(message: 'Give the app a name, at most 255 characters.');
		}

		$linkType  = (string)($input['linkType'] ?? 'url');
		$linkValue = trim(string: (string)($input['linkValue'] ?? ''));
		if ($linkType !== 'app' && $linkType !== 'url') {
			throw new InvalidArgumentException(message: 'The address is a Nextcloud app or a web address.');
		}

		if ($linkType === 'url' && preg_match(pattern: '#^https?://[^\s/$.?\#].[^\s]*$#i', subject: $linkValue) !== 1) {
			throw new InvalidArgumentException(message: 'Give a web address that starts with http:// or https://.');
		}

		if ($linkType === 'app' && preg_match(pattern: '/^[a-z0-9_-]+$/i', subject: $linkValue) !== 1) {
			throw new InvalidArgumentException(message: 'Give the id of a Nextcloud app.');
		}

		$iconType = (string)($input['iconType'] ?? 'class');
		if ($iconType !== 'class' && $iconType !== 'url') {
			$iconType = 'class';
		}

		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$app->setName($name);
		$app->setDescription(trim(string: (string)($input['description'] ?? '')));
		$app->setIcon(trim(string: (string)($input['icon'] ?? '')));
		$app->setIconType($iconType);
		$app->setLinkType($linkType);
		$app->setLinkValue($linkValue);
		$app->setTargetGroups(json_encode(value: self::groupList(raw: ($input['targetGroups'] ?? []))));
		$app->setSortOrder((int)($input['sortOrder'] ?? 0));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
	}//end apply()

	/**
	 * A list of distinct, non-empty group ids.
	 *
	 * @param mixed $raw The submitted groups.
	 *
	 * @return array<int, string>
	 */
	private static function groupList(mixed $raw): array {
		if (is_array($raw) === false) {
			return [];
		}

		$groups = [];
		foreach ($raw as $group) {
			// A group picker option arrives as {id, displayName}.
			if (is_array($group) === true) {
				$group = ($group['id'] ?? '');
			}

			$group = trim(string: (string)$group);
			if ($group !== '') {
				$groups[$group] = true;
			}
		}

		return array_keys($groups);
	}//end groupList()

	/**
	 * A request that is still open.
	 *
	 * @param string $uuid The request.
	 *
	 * @return AppRequest
	 *
	 * @throws DomainException When it was already decided (code `request_not_open`).
	 */
	private function openRequest(string $uuid): AppRequest {
		$request = $this->requestMapper->findByUuid(uuid: $uuid);
		if ($request->getStatus() !== 'open') {
			throw new DomainException(message: self::ERROR_NOT_OPEN);
		}

		return $request;
	}//end openRequest()

	/**
	 * Who hears about a new request: every administrator and the members of
	 * the catalogue manager group, each once.
	 *
	 * @return array<int, string> User ids.
	 */
	private function requestRecipients(): array {
		$recipients = [];
		foreach (['admin', $this->getManagerGroup()] as $groupId) {
			if ($groupId === '') {
				continue;
			}

			$group = $this->groupManager->get(gid: $groupId);
			if ($group === null) {
				continue;
			}

			foreach ($group->getUsers() as $user) {
				$recipients[$user->getUID()] = true;
			}
		}

		return array_keys($recipients);
	}//end requestRecipients()

	/**
	 * Send one notification. A notification that cannot be sent is logged
	 * and does not undo the request or the decision.
	 *
	 * @param string             $userId      The recipient.
	 * @param string             $subject     `app_requested` or `app_request_decided`.
	 * @param array<int, string> $params      The subject parameters.
	 * @param string             $requestUuid The request.
	 *
	 * @return void
	 */
	private function notify(string $userId, string $subject, array $params, string $requestUuid): void {
		try {
			$notification = $this->notificationManager->createNotification();
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new DateTime())
				->setObject('app_request', $requestUuid)
				->setSubject($subject, $params);
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$this->notificationManager->notify(notification: $notification);
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'LaunchPad could not send the app catalogue notification: ' . $e->getMessage(),
				context: ['exception' => $e]
			);
		}
	}//end notify()

	/**
	 * The current time as stored.
	 *
	 * @return string
	 */
	private static function now(): string {
		return (new DateTime())->format(format: 'Y-m-d H:i:s');
	}//end now()
}//end class
