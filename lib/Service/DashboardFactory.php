<?php

/**
 * DashboardFactory
 *
 * Factory service for creating dashboard entities. Enforces the
 * `(type, groupId)` invariant required by REQ-DASH-011.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;

/**
 * Factory service for creating dashboard entities.
 */
class DashboardFactory {
	/**
	 * The optional create() fields and their defaults.
	 */
	private const DEFAULT_OPTIONS = [
		'description'     => null,
		'type'            => Dashboard::TYPE_USER,
		'groupId'         => null,
		'gridColumns'     => 12,
		'permissionLevel' => Dashboard::PERMISSION_FULL,
		'parentUuid'      => null,
		'slug'            => null,
		'sortOrder'       => 0,
	];

	/**
	 * Create a new dashboard entity.
	 *
	 * Enforces the REQ-DASH-011 invariant: `type === TYPE_GROUP_SHARED`
	 * iff `groupId !== null`. Throws `InvalidArgumentException` on
	 * mismatch — no row is persisted in that case (the caller never
	 * receives an entity to insert).
	 *
	 * @param string|null $userId The user ID — must be non-null for
	 *                            `TYPE_USER`, must be null for
	 *                            `TYPE_GROUP_SHARED` /
	 *                            `TYPE_ADMIN_TEMPLATE`.
	 * @param string $name The dashboard name.
	 * @param array $options Optional fields, each with its default:
	 *                       `description` (null); `type`
	 *                       ({@see Dashboard::TYPE_USER}); `groupId` (null,
	 *                       required when `type === TYPE_GROUP_SHARED`,
	 *                       forbidden otherwise); `gridColumns` (12);
	 *                       `permissionLevel` (the owner's level,
	 *                       {@see Dashboard::PERMISSION_FULL}); `parentUuid`
	 *                       (null ⇒ root, REQ-DASH-023; the factory does
	 *                       not verify the parent or the depth); `slug`
	 *                       (null ⇒ derived from the name via
	 *                       `SlugGenerator`, REQ-DASH-024; an explicit slug
	 *                       that fails the grammar is rejected); `sortOrder`
	 *                       (0, REQ-DASH-029).
	 *
	 * @return Dashboard The created dashboard entity (not yet persisted).
	 *
	 * @throws InvalidArgumentException When the (type, groupId) invariant
	 *                                  is violated, or when an explicit
	 *                                  slug fails the grammar check.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-16
	 */
	public function create(?string $userId, string $name, array $options=[]): Dashboard {
		$options += self::DEFAULT_OPTIONS;
		$type     = $options['type'];
		$this->assertTypeGroupInvariant(type: $type, groupId: $options['groupId']);

		$resolvedSlug = $this->resolveSlug(name: $name, slug: $options['slug']);

		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$dashboard = new Dashboard();
		$dashboard->setUuid($this->generateUuid());
		$dashboard->setName($name);
		$dashboard->setDescription($options['description']);
		$dashboard->setType($type);
		$dashboard->setUserId($userId);
		$dashboard->setGroupId($options['groupId']);
		$dashboard->setGridColumns($options['gridColumns']);
		$dashboard->setPermissionLevel($options['permissionLevel']);
		// Group-shared dashboards are not "active" per-user — activation
		// is a personal-scope concept tied to the active-dashboard cookie.
		$isActive = 0;
		if ($type === Dashboard::TYPE_USER) {
			$isActive = 1;
		}

		$dashboard->setIsActive($isActive);
		$dashboard->setCreatedAt($now);
		$dashboard->setUpdatedAt($now);
		$dashboard->setParentUuid($options['parentUuid']);
		$dashboard->setSlug($resolvedSlug);
		$dashboard->setSortOrder($options['sortOrder']);

		// REQ-DASH-031 / design D2: every newly created dashboard MUST
		// default to publicationStatus = 'draft'. The database column
		// default is `'published'` (REQ-DASH-035) — the application
		// overrides it here so the "create now, share later" contract
		// holds for every new row regardless of how it gets persisted.
		$dashboard->setPublicationStatus(Dashboard::STATUS_DRAFT);

		return $dashboard;
	}//end create()

	/**
	 * Resolve the slug for a new dashboard.
	 *
	 * - Caller-supplied non-empty slug ⇒ validated against the grammar
	 *   (`SlugGenerator::isValid()`) and used verbatim.
	 * - Caller-supplied empty / NULL slug ⇒ derived from the name via
	 *   `SlugGenerator::slugify()`. When the name yields no legal
	 *   characters the resulting slug is NULL — the service layer will
	 *   keep it NULL and the row stays unaddressable by path until the
	 *   user supplies one (the path API returns 404 instead).
	 *
	 * @param string $name The dashboard name.
	 * @param string|null $slug The caller-supplied slug, or NULL.
	 *
	 * @return string|null The slug to persist, or NULL when neither path
	 *                     yielded a legal value.
	 *
	 * @throws InvalidArgumentException When an explicit slug fails the
	 *                                  REQ-DASH-024 grammar check.
	 */
	private function resolveSlug(string $name, ?string $slug): ?string {
		if ($slug !== null && $slug !== '') {
			if (SlugGenerator::isValid(slug: $slug) === false) {
				throw new InvalidArgumentException(
					message: 'Slug must match [a-z0-9_-]+ and be ≤128 characters'
				);
			}

			return $slug;
		}

		$derived = SlugGenerator::slugify(name: $name);
		if ($derived === '') {
			return null;
		}

		return $derived;
	}//end resolveSlug()

	/**
	 * Assert the (type, groupId) invariant of REQ-DASH-011.
	 *
	 * @param string $type The dashboard type.
	 * @param string|null $groupId The group ID.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the invariant is violated.
	 */
	private function assertTypeGroupInvariant(
		string $type,
		?string $groupId,
	): void {
		if ($type === Dashboard::TYPE_GROUP_SHARED) {
			if ($groupId === null || $groupId === '') {
				throw new InvalidArgumentException(
					message: 'Dashboard type group_shared requires a non-empty groupId'
				);
			}

			return;
		}

		if ($groupId !== null) {
			throw new InvalidArgumentException(
				message: 'Dashboard type ' . $type . ' must not have a groupId'
			);
		}
	}//end assertTypeGroupInvariant()

	/**
	 * Generate a UUID v4.
	 *
	 * @return string The generated UUID.
	 */
	private function generateUuid(): string {
		$data = random_bytes(length: 16);
		$data[6] = chr(codepoint: ord(character: $data[6]) & 0x0f | 0x40);
		$data[8] = chr(codepoint: ord(character: $data[8]) & 0x3f | 0x80);

		return vsprintf(
			format: '%s%s-%s-%s-%s-%s%s%s',
			values: str_split(string: bin2hex(string: $data), length: 4)
		);
	}//end generateUuid()
}//end class
