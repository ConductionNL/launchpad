<?php

/**
 * PeopleWidgetService
 *
 * Backend service for the People widget (capability `people-widget`).
 *
 * Resolves a paginated, group-filterable directory of Nextcloud users with
 * profile cards built from `OCP\Accounts\IAccountManager`. The service is
 * the single contact point between the {@see PeopleWidgetController} HTTP
 * layer and Nextcloud's user / group / account managers.
 *
 * Visibility model (REQ-PPL-004): v1 returns every non-empty profile field
 * unconditionally — scope-based (`$prop->getScope()`) filtering is a
 * documented follow-up. Empty / null values are still omitted from the
 * response, never returned as `null` keys.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use OCP\Accounts\IAccountManager;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Service for the People widget user-directory listing (REQ-PPL-001..012).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   The widget aggregates
 *  user, account, group, and URL-generator dependencies — coupling matches
 *  the spec's response surface and is unavoidable.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) The class encapsulates
 *  argument validation, candidate resolution, sorting, account-field
 *  projection, birthdate normalisation, and avatar URL construction in one
 *  cohesive unit — splitting would force the controller to weave the same
 *  state across multiple services.
 * @spec                                             openspec/specs/people-widget/spec.md
 */
class PeopleWidgetService
{
    /**
     * Hard ceiling on the `limit` query parameter (REQ-PPL-003).
     *
     * The shipping reference allows max 100 per page; we keep that.
     *
     * @var int
     */
    public const MAX_LIMIT = 100;

    /**
     * Default page size when the caller omits `limit`.
     *
     * @var int
     */
    public const DEFAULT_LIMIT = 50;

    /**
     * Avatar fetch resolution (REQ-PPL-007). Display size is layout-driven
     * client-side; the URL we return always points at the 128 px endpoint
     * so the same URL works for card / grid / list at retina densities.
     *
     * @var int
     */
    public const AVATAR_SIZE_PX = 128;

    /**
     * Standard `IAccountManager` properties projected into the response.
     *
     * Order is irrelevant — the response is a flat object — but the
     * constant doubles as the iteration list inside
     * {@see self::buildAccountFields()} so we keep it tightly scoped to
     * properties the widget actually surfaces.
     *
     * @var string[]
     */
    private const STANDARD_PROPERTIES = [
        IAccountManager::PROPERTY_PHONE,
        IAccountManager::PROPERTY_ADDRESS,
        IAccountManager::PROPERTY_WEBSITE,
        IAccountManager::PROPERTY_TWITTER,
        IAccountManager::PROPERTY_FEDIVERSE,
        IAccountManager::PROPERTY_ORGANISATION,
        IAccountManager::PROPERTY_ROLE,
        IAccountManager::PROPERTY_HEADLINE,
        IAccountManager::PROPERTY_BIOGRAPHY,
        IAccountManager::PROPERTY_PRONOUNS,
        IAccountManager::PROPERTY_BIRTHDATE,
    ];

    /**
     * Constructor.
     *
     * @param IUserManager         $userManager          Nextcloud user manager.
     * @param IGroupManager        $groupManager         Nextcloud group manager.
     * @param IAccountManager      $accountManager       Nextcloud account / profile manager.
     * @param IURLGenerator        $urlGenerator         Absolute-URL helper for avatars.
     * @param AdminTemplateService $adminTemplateService Single-source-of-truth wrapper around
     *                                                   `IGroupManager::getUserGroupIds`
     *                                                   (REQ-TMPL-013 grep guard).
     */
    public function __construct(
        private readonly IUserManager $userManager,
        private readonly IGroupManager $groupManager,
        private readonly IAccountManager $accountManager,
        private readonly IURLGenerator $urlGenerator,
        private readonly AdminTemplateService $adminTemplateService,
    ) {
    }//end __construct()

    /**
     * List visible users matching the placement configuration.
     *
     * REQ-PPL-003: offset-based pagination, capped at {@see self::MAX_LIMIT}.
     * REQ-PPL-006: `filters` array supports `{fieldName: 'group', operator:
     * 'in', values: [...]}` to restrict the candidate pool to the union of
     * the listed group memberships.
     *
     * Response shape: `{users: [...], total: int, hasMore: bool}`.
     *
     * @param array  $filters         Filter predicate list. Each entry has
     *                                the shape `{fieldName, operator, values}`.
     * @param bool   $excludeDisabled True (default) excludes disabled NC
     *                                users from the candidate pool.
     * @param bool   $showBirthdays   False strips the birthdate field from
     *                                every result row.
     * @param string $sortBy          One of `displayName` (default),
     *                                `group`, or `recent-activity`.
     * @param int    $limit           Page size (1..MAX_LIMIT).
     * @param int    $offset          Page offset (>=0).
     *
     * @return array Pagination envelope with keys `users`, `total`, `hasMore`.
     *
     * @throws InvalidArgumentException When `limit` exceeds MAX_LIMIT, is
     *                                  non-positive, or `offset` is negative;
     *                                  or when `sortBy` is `recent-activity`.
     *
     * @spec openspec/specs/people-widget/spec.md
     */
    public function listUsers(
        array $filters=[],
        bool $excludeDisabled=true,
        bool $showBirthdays=true,
        string $sortBy='displayName',
        int $limit=self::DEFAULT_LIMIT,
        int $offset=0,
    ): array {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException(
                message: 'limit must be between 1 and '.self::MAX_LIMIT
            );
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(message: 'offset must be >= 0');
        }

        if ($sortBy === 'recent-activity') {
            throw new InvalidArgumentException(
                message: 'sortBy=recent-activity is not yet implemented'
            );
        }

        $groupFilter = $this->extractGroupFilter(filters: $filters);

        // No `group` filter AND the default `displayName` sort: page
        // directly from the backend in display-name order so we never
        // materialize more IUser objects than the requested window
        // (plus any disabled users skipped inside it). This avoids the
        // former full-directory `IUserManager::search('')` scan on every
        // People-widget render. REQ-PPL-003.
        if ($groupFilter === null && $sortBy !== 'group') {
            return $this->listDirectoryPageByDisplayName(
                excludeDisabled: $excludeDisabled,
                showBirthdays: $showBirthdays,
                limit: $limit,
                offset: $offset
            );
        }

        // Bounded candidate pool: either the union of the filtered group
        // memberships, or — only for the rare admin-selected `group` sort
        // with no group filter — the full directory, since ordering by
        // each user's primary-group membership is inherently a
        // full-directory read (see resolveCandidates()).
        $candidates = $this->resolveCandidates(
            filters: $filters,
            groupFilter: $groupFilter
        );

        return $this->paginateCandidates(
            candidates: $candidates,
            excludeDisabled: $excludeDisabled,
            showBirthdays: $showBirthdays,
            sortBy: $sortBy,
            limit: $limit,
            offset: $offset
        );
    }//end listUsers()

    /**
     * Page the directory directly from the backend in display-name order,
     * bounded to the requested window.
     *
     * `IUserManager::searchDisplayName('', limit, offset)` returns users
     * already sorted by display name with backend-level pagination, so a
     * bounded fetch is the correct global-order window (unlike
     * `search('')`, which returns backend/UID order). Disabled users are
     * skipped inside the streamed window when `excludeDisabled` is true;
     * the exact `total` is derived from `countUsersTotal()` (minus
     * `countDisabledUsers()`) so no full scan is needed to size it.
     *
     * @param bool $excludeDisabled Whether disabled users are excluded.
     * @param bool $showBirthdays   Whether birthdate is projected.
     * @param int  $limit           Page size (already validated 1..MAX_LIMIT).
     * @param int  $offset          Page offset (already validated >= 0).
     *
     * @return array Pagination envelope with keys `users`, `total`, `hasMore`.
     */
    private function listDirectoryPageByDisplayName(
        bool $excludeDisabled,
        bool $showBirthdays,
        int $limit,
        int $offset
    ): array {
        $page  = $this->collectDisplayNamePage(
            excludeDisabled: $excludeDisabled,
            limit: $limit,
            offset: $offset
        );
        $total = $this->countDirectory(excludeDisabled: $excludeDisabled);

        $users = [];
        foreach ($page as $user) {
            $users[] = $this->buildUserProfile(
                user: $user,
                showBirthdays: $showBirthdays
            );
        }

        return [
            'users'   => $users,
            'total'   => $total,
            'hasMore' => ($offset + count(value: $page)) < $total,
        ];
    }//end listDirectoryPageByDisplayName()

    /**
     * Stream `searchDisplayName` in bounded chunks, skipping disabled
     * users (when requested) and the first `$offset` matches, until
     * `$limit` users are collected or the backend is exhausted.
     *
     * Reads at most `offset + limit` matches (plus any disabled users
     * interleaved in that window) — never the full user table.
     *
     * @param bool $excludeDisabled Whether disabled users are skipped.
     * @param int  $limit           Number of users to collect.
     * @param int  $offset          Number of matching users to skip first.
     *
     * @return IUser[] The requested page in display-name order.
     */
    private function collectDisplayNamePage(
        bool $excludeDisabled,
        int $limit,
        int $offset
    ): array {
        $chunkSize     = max($limit, self::DEFAULT_LIMIT);
        $page          = [];
        $toSkip        = $offset;
        $backendOffset = 0;

        while (count(value: $page) < $limit) {
            $batch      = $this->userManager->searchDisplayName(
                pattern: '',
                limit: $chunkSize,
                offset: $backendOffset
            );
            $batchCount = count(value: $batch);
            if ($batchCount === 0) {
                break;
            }

            foreach ($batch as $user) {
                if ($excludeDisabled === true && $user->isEnabled() === false) {
                    continue;
                }

                if ($toSkip > 0) {
                    $toSkip--;
                    continue;
                }

                $page[] = $user;
                if (count(value: $page) >= $limit) {
                    break;
                }
            }

            $backendOffset += $batchCount;
            if ($batchCount < $chunkSize) {
                // Backend exhausted — no more pages to stream.
                break;
            }
        }//end while

        return $page;
    }//end collectDisplayNamePage()

    /**
     * Exact directory size for the pagination envelope, computed from the
     * backend counters rather than by materializing every user.
     *
     * @param bool $excludeDisabled When true, disabled users are excluded
     *                              from the count.
     *
     * @return int The total candidate count.
     */
    private function countDirectory(bool $excludeDisabled): int
    {
        $total = $this->userManager->countUsersTotal();
        if (is_int(value: $total) === false) {
            // Some backends cannot report a total (countUsersTotal()
            // returns false); fall back to zero so the envelope stays
            // finite and non-negative.
            $total = 0;
        }

        if ($excludeDisabled === true) {
            $total -= (int) $this->userManager->countDisabledUsers();
        }

        return max(0, $total);
    }//end countDirectory()

    /**
     * Filter → sort → slice → project an in-memory candidate pool.
     *
     * Used for the group-filtered path (pool already bounded to group
     * membership) and the rare no-filter `group` sort path.
     *
     * @param IUser[] $candidates      The resolved candidate pool.
     * @param bool    $excludeDisabled Whether disabled users are excluded.
     * @param bool    $showBirthdays   Whether birthdate is projected.
     * @param string  $sortBy          Sort mode (`displayName` or `group`).
     * @param int     $limit           Page size.
     * @param int     $offset          Page offset.
     *
     * @return array Pagination envelope with keys `users`, `total`, `hasMore`.
     */
    private function paginateCandidates(
        array $candidates,
        bool $excludeDisabled,
        bool $showBirthdays,
        string $sortBy,
        int $limit,
        int $offset
    ): array {
        if ($excludeDisabled === true) {
            $candidates = array_values(
                array: array_filter(
                    array: $candidates,
                    callback: static fn(IUser $candidate): bool => $candidate->isEnabled() === true
                )
            );
        }

        $candidates = $this->sortCandidates(users: $candidates, sortBy: $sortBy);

        $total = count(value: $candidates);
        $page  = array_slice(
            array: $candidates,
            offset: $offset,
            length: $limit
        );

        $users = [];
        foreach ($page as $user) {
            $users[] = $this->buildUserProfile(
                user: $user,
                showBirthdays: $showBirthdays
            );
        }

        return [
            'users'   => $users,
            'total'   => $total,
            'hasMore' => ($offset + count(value: $page)) < $total,
        ];
    }//end paginateCandidates()

    /**
     * Compute the number of days between today (UTC) and the user's next
     * birthday. Returns `null` for invalid / missing input.
     *
     * Wraps to next year when the birthday already passed this year. Feb-29
     * birthdays in non-leap years substitute Feb-28 to avoid throwing.
     *
     * @param string|null $birthdate ISO-8601 date string (e.g. "1990-06-10"),
     *                               locale-formatted (e.g. "10-06-1990"), or
     *                               null when the user has no birthdate.
     *
     * @return int|null Days until next birthday, or null when input is
     *                  invalid.
     *
     * @spec openspec/specs/people-widget/spec.md
     */
    public static function computeDaysToBirthday(?string $birthdate): ?int
    {
        $iso = self::normaliseToIsoDate(raw: $birthdate);
        if ($iso === null) {
            return null;
        }

        try {
            $birth = new DateTimeImmutable(datetime: $iso);
        } catch (Exception $e) {
            return null;
        }

        $today = new DateTimeImmutable(datetime: 'today');

        $monthDay = $birth->format(format: 'm-d');
        // Feb-29 guard: in non-leap target years, fall back to Feb-28.
        $candidate = self::buildBirthdayInYear(
            year: (int) $today->format(format: 'Y'),
            monthDay: $monthDay
        );

        if ($candidate < $today) {
            $candidate = self::buildBirthdayInYear(
                year: ((int) $today->format(format: 'Y')) + 1,
                monthDay: $monthDay
            );
        }

        $diff = $today->diff(targetObject: $candidate);
        return (int) $diff->days;
    }//end computeDaysToBirthday()

    /**
     * Extract the first `group` filter from the filter list, if present.
     *
     * @param array $filters Filter list (entries shaped like
     *                       `{fieldName, operator, values}`).
     *
     * @return array|null The `group` filter entry, or null when absent.
     */
    private function extractGroupFilter(array $filters): ?array
    {
        foreach ($filters as $filter) {
            if (($filter['fieldName'] ?? null) === 'group') {
                return $filter;
            }
        }

        return null;
    }//end extractGroupFilter()

    /**
     * Resolve the candidate pool based on the configured filters.
     *
     * When a `group` filter is present we use
     * `IGroupManager::get($gid)->getUsers()` to bound the pool to the
     * union of the listed group memberships (deduplicated by UID via a
     * `$seen` map) — never scanning the full user table.
     *
     * Without a group filter this method is reached ONLY for the rare
     * admin-selected `group` sort mode; the common `displayName` path is
     * served by the bounded backend pagination in
     * {@see self::listDirectoryPageByDisplayName()} and never calls here.
     * Ordering the whole directory by each user's primary-group
     * membership is inherently a full-directory read, so this path falls
     * back to `IUserManager::search('')` by design.
     *
     * @param array      $filters     Filter list (entries shaped like
     *                                `{fieldName, operator, values}`).
     * @param array|null $groupFilter The pre-extracted `group` filter, or
     *                                null when none is configured.
     *
     * @return IUser[]
     */
    private function resolveCandidates(array $filters, ?array $groupFilter=null): array
    {
        if ($groupFilter === null) {
            $groupFilter = $this->extractGroupFilter(filters: $filters);
        }

        if ($groupFilter === null) {
            return $this->userManager->search(pattern: '');
        }

        $values = $groupFilter['values'] ?? [];
        if (is_array(value: $values) === false || $values === []) {
            return [];
        }

        $seen   = [];
        $result = [];
        foreach ($values as $groupId) {
            if (is_string(value: $groupId) === false || $groupId === '') {
                continue;
            }

            $group = $this->groupManager->get(gid: $groupId);
            if ($group === null) {
                // Unknown group MUST yield zero users for that value
                // without raising — REQ-PPL-006 scenario "Unknown group
                // name handled gracefully".
                continue;
            }

            foreach ($group->getUsers() as $user) {
                $uid = $user->getUID();
                if (isset($seen[$uid]) === true) {
                    continue;
                }

                $seen[$uid] = true;
                $result[]   = $user;
            }
        }//end foreach

        return $result;
    }//end resolveCandidates()

    /**
     * Stable ordering helper.
     *
     * @param IUser[] $users  Candidate list.
     * @param string  $sortBy One of `displayName` (default) or `group`.
     *
     * @return IUser[]
     */
    private function sortCandidates(array $users, string $sortBy): array
    {
        if ($sortBy === 'group') {
            usort(
                array: $users,
                callback: function (IUser $left, IUser $right): int {
                    $leftGroup  = $this->primaryGroup(user: $left);
                    $rightGroup = $this->primaryGroup(user: $right);
                    if ($leftGroup === $rightGroup) {
                        return strcasecmp(
                            string1: $left->getDisplayName(),
                            string2: $right->getDisplayName()
                        );
                    }

                    return strcasecmp(string1: $leftGroup, string2: $rightGroup);
                }
            );

            return $users;
        }

        // Default: displayName, case-insensitive ASCII collation.
        usort(
            array: $users,
            callback: static fn(IUser $left, IUser $right): int => strcasecmp(
                string1: $left->getDisplayName(),
                string2: $right->getDisplayName()
            )
        );

        return $users;
    }//end sortCandidates()

    /**
     * First (alphabetical) group id the user belongs to, or empty string.
     *
     * @param IUser $user The user.
     *
     * @return string Lowercased group id used as the sort bucket.
     */
    private function primaryGroup(IUser $user): string
    {
        $groups = $this->adminTemplateService->getUserGroupIdsFor(
            userId: $user->getUID()
        );
        if ($groups === []) {
            return '';
        }

        sort(array: $groups, flags: SORT_NATURAL | SORT_FLAG_CASE);
        return strtolower(string: $groups[0]);
    }//end primaryGroup()

    /**
     * Project a single user to the response shape.
     *
     * @param IUser $user          The user to project.
     * @param bool  $showBirthdays When false, omits the birthdate field
     *                             unconditionally (REQ-PPL-005).
     *
     * @return array<string, mixed>
     */
    private function buildUserProfile(IUser $user, bool $showBirthdays): array
    {
        $uid = $user->getUID();

        $profile = [
            'uid'         => $uid,
            'displayName' => $user->getDisplayName(),
            'avatarUrl'   => $this->buildAvatarUrl(uid: $uid),
            'groups'      => array_values(
                array: $this->adminTemplateService->getUserGroupIdsFor(userId: $uid)
            ),
        ];

        $email = $user->getEMailAddress();
        if (is_string(value: $email) === true && $email !== '') {
            $profile['email'] = $email;
        }

        $accountFields = $this->buildAccountFields(
            user: $user,
            showBirthdays: $showBirthdays
        );

        return array_merge($profile, $accountFields);
    }//end buildUserProfile()

    /**
     * Read the standard `IAccountManager` properties for a user, omitting
     * empty values. Birthdate is normalised to ISO-8601.
     *
     * @param IUser $user          The user.
     * @param bool  $showBirthdays When false, the birthdate property is
     *                             skipped even if set.
     *
     * @return array<string, string>
     */
    private function buildAccountFields(IUser $user, bool $showBirthdays): array
    {
        try {
            $account = $this->accountManager->getAccount(user: $user);
        } catch (Exception $e) {
            return [];
        }

        $fields = [];
        foreach (self::STANDARD_PROPERTIES as $property) {
            if ($property === IAccountManager::PROPERTY_BIRTHDATE
                && $showBirthdays === false
            ) {
                continue;
            }

            try {
                $prop  = $account->getProperty(property: $property);
                $value = $prop->getValue();
            } catch (Exception $e) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            if ($property === IAccountManager::PROPERTY_BIRTHDATE) {
                $iso = self::normaliseToIsoDate(raw: $value);
                if ($iso === null) {
                    continue;
                }

                $fields['birthdate'] = $iso;
                continue;
            }

            $fields[$property] = $value;
        }//end foreach

        return $fields;
    }//end buildAccountFields()

    /**
     * Build an absolute avatar URL pointing at the standard NC route.
     *
     * @param string $uid The user id.
     *
     * @return string Absolute URL to the 128 px avatar endpoint.
     */
    private function buildAvatarUrl(string $uid): string
    {
        return $this->urlGenerator->linkToRouteAbsolute(
            routeName: 'core.avatar.getAvatar',
            arguments: [
                'userId' => $uid,
                'size'   => self::AVATAR_SIZE_PX,
            ]
        );
    }//end buildAvatarUrl()

    /**
     * Best-effort conversion of a stored birthdate string to ISO-8601
     * (`YYYY-MM-DD`). Accepts common locale formats (`DD-MM-YYYY`,
     * `DD/MM/YYYY`, `DD.MM.YYYY`, `YYYY-MM-DD`). Returns null on failure
     * so callers can omit the field cleanly.
     *
     * @param string|null $raw Stored birthdate value.
     *
     * @return string|null ISO date string or null when the input cannot
     *                     be parsed.
     */
    private static function normaliseToIsoDate(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim(string: $raw);
        if ($trimmed === '') {
            return null;
        }

        // Already ISO?
        if (preg_match(pattern: '/^\d{4}-\d{2}-\d{2}$/', subject: $trimmed) === 1) {
            return $trimmed;
        }

        $separators = ['-', '/', '.'];
        foreach ($separators as $sep) {
            $parts = explode(separator: $sep, string: $trimmed);
            if (count(value: $parts) !== 3) {
                continue;
            }

            // Heuristic: 4-digit segment is the year. Position determines
            // whether DMY or YMD ordering applies.
            if (strlen(string: $parts[2]) !== 4 && strlen(string: $parts[0]) !== 4) {
                continue;
            }

            $day   = '';
            $month = '';
            $year  = '';
            if (strlen(string: $parts[2]) === 4) {
                $day   = $parts[0];
                $month = $parts[1];
                $year  = $parts[2];
            }

            if (strlen(string: $parts[0]) === 4) {
                $year  = $parts[0];
                $month = $parts[1];
                $day   = $parts[2];
            }

            if (ctype_digit(text: $year) === false
                || ctype_digit(text: $month) === false
                || ctype_digit(text: $day) === false
            ) {
                continue;
            }

            $iso = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
            if (checkdate(month: (int) $month, day: (int) $day, year: (int) $year) === false) {
                continue;
            }

            return $iso;
        }//end foreach

        return null;
    }//end normaliseToIsoDate()

    /**
     * Construct a `DateTimeImmutable` for the given month-day in the
     * supplied year, falling back to Feb-28 when the requested year is
     * not a leap year and the input is Feb-29.
     *
     * @param int    $year     Target year.
     * @param string $monthDay `MM-DD` slug (no validation; trusted caller).
     *
     * @return DateTimeImmutable
     */
    private static function buildBirthdayInYear(
        int $year,
        string $monthDay
    ): DateTimeImmutable {
        [$month, $day] = explode(separator: '-', string: $monthDay);
        $monthInt      = (int) $month;
        $dayInt        = (int) $day;

        if ($monthInt === 2 && $dayInt === 29
            && checkdate(month: 2, day: 29, year: $year) === false
        ) {
            $dayInt = 28;
        }

        return new DateTimeImmutable(
            datetime: sprintf('%04d-%02d-%02d', $year, $monthInt, $dayInt)
        );
    }//end buildBirthdayInYear()
}//end class
