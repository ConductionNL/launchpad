<?php

/**
 * CalendarWidgetService
 *
 * Aggregates events for the LaunchPad calendar widget from internal Nextcloud
 * Calendar sources (via OCP\Calendar\IManager when available) and external
 * ICS feeds. Recurring events are expanded server-side using sabre/vobject's
 * VCalendar::expand() (bundled with Nextcloud — not vendored separately).
 *
 * Calls are scoped to an explicit `from..to` window (capped at one year by
 * the controller) and external ICS responses are cached for 30 minutes via
 * IAppConfig-tunable TTL. SSRF safety on external URLs is enforced through:
 *   1. HTTPS-only URL acceptance.
 *   2. DNS resolution + private/reserved IP rejection.
 *   3. Optional admin allow-list of hostnames (empty = all HTTPS hosts).
 *   4. 1 MB response cap.
 *
 * A failure on any single source is contained: it is logged and that source
 * is dropped from the response, but other sources continue to render.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\LaunchPad\AppInfo\Application;
use OCP\Calendar\IManager;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Aggregates and normalises calendar events for the LaunchPad calendar widget.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   Calendar + HTTP + cache +
 *                                                 config + logging are all
 *                                                 unavoidable here.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Aggregation of multiple
 *                                                   sources + recurring-
 *                                                   event expansion + SSRF
 *                                                   guard naturally pushes
 *                                                   total complexity past
 *                                                   the project default.
 */
class CalendarWidgetService
{
    /**
     * Default cache TTL for raw ICS bodies (in seconds).
     *
     * @var int
     */
    public const DEFAULT_CACHE_TTL_SECONDS = 1800;

    /**
     * App-config key for the admin-tunable cache TTL.
     *
     * @var string
     */
    public const CONFIG_KEY_CACHE_TTL = 'calendar_widget_ics_cache_ttl_seconds';

    /**
     * App-config key for the JSON-encoded allow-list of ICS hostnames.
     *
     * @var string
     */
    public const CONFIG_KEY_ALLOWED_HOSTS = 'calendar_widget_allowed_ics_hosts';

    /**
     * HTTP timeout for external ICS fetches (seconds).
     *
     * @var int
     */
    public const FETCH_TIMEOUT_SECONDS = 10;

    /**
     * Maximum response size accepted from an external ICS URL (bytes).
     *
     * @var int
     */
    public const MAX_RESPONSE_SIZE_BYTES = 1048576;

    /**
     * Cache namespace used by ICacheFactory::createDistributed().
     *
     * @var string
     */
    private const CACHE_NAMESPACE = 'launchpad-calendar-ics';

    /**
     * The distributed ICache instance used to cache raw ICS bodies.
     *
     * @var ICache
     */
    private ICache $cache;

    /**
     * Constructor.
     *
     * @param IAppConfig         $appConfig     The app config reader.
     * @param ICacheFactory      $cacheFactory  Distributed cache factory.
     * @param IClientService     $clientService HTTP client factory.
     * @param LoggerInterface    $logger        Logger.
     * @param UrlSafetyValidator $urlValidator  Shared SSRF / allow-list guard.
     * @param IManager|null      $calendarMgr   NC Calendar manager (optional).
     */
    public function __construct(
        private readonly IAppConfig $appConfig,
        ICacheFactory $cacheFactory,
        private readonly IClientService $clientService,
        private readonly LoggerInterface $logger,
        private readonly UrlSafetyValidator $urlValidator,
        private readonly ?IManager $calendarMgr=null,
    ) {
        $this->cache = $cacheFactory->createDistributed(prefix: self::CACHE_NAMESPACE);
    }//end __construct()

    /**
     * Aggregate events for a single placement configuration.
     *
     * Combines internal NC calendar events and external ICS events.
     * Failures on individual sources are logged and reported via
     * the `failures` array but never abort the whole call.
     *
     * @param array  $config Parsed calendar widget config — keys:
     *                       internalCalendars: string[],
     *                       externalIcsUrls: string[].
     * @param string $from   ISO 8601 start (inclusive).
     * @param string $to     ISO 8601 end (inclusive).
     *
     * @return array{events: array<int, array<string, mixed>>, failures: array<int, string>}
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function getEvents(array $config, string $from, string $to): array
    {
        $internalCalendars = (array) ($config['internalCalendars'] ?? []);
        $externalIcsUrls   = (array) ($config['externalIcsUrls'] ?? []);

        $events   = [];
        $failures = [];

        if ($internalCalendars !== []) {
            $internalResult = $this->fetchInternalEvents(
                principals: $internalCalendars,
                from: $from,
                to: $to
            );
            $events         = array_merge($events, $internalResult['events']);
            $failures       = array_merge($failures, $internalResult['failures']);
        }

        if ($externalIcsUrls !== []) {
            $externalResult = $this->fetchExternalIcsEvents(
                urls: $externalIcsUrls,
                from: $from,
                to: $to
            );
            $events         = array_merge($events, $externalResult['events']);
            $failures       = array_merge($failures, $externalResult['failures']);
        }

        usort(
            array: $events,
            callback: static function (array $eventA, array $eventB): int {
                return strcmp(string1: $eventA['start'] ?? '', string2: $eventB['start'] ?? '');
            }
        );

        return [
            'events'   => $events,
            'failures' => $failures,
        ];
    }//end getEvents()

    /**
     * List the current user's internal calendars for the config-form picker
     * (REQ-CAL-002). Each entry's `key` is the identifier
     * {@see fetchInternalEvents} filters on (the search result's
     * `calendar-key`). Returns an empty list when the Calendar app is absent.
     *
     * @param string $userId The current user id.
     *
     * @return array<int, array{key: string, name: string, color: string}>
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function listCalendars(string $userId): array
    {
        if ($this->calendarMgr === null) {
            return [];
        }

        $out = [];
        try {
            $calendars = $this->calendarMgr->getCalendarsForPrincipal(
                principalUri: 'principals/users/'.$userId
            );
        } catch (Throwable $exception) {
            $this->logger->warning(
                message: 'Calendar widget: listing calendars failed: '.$exception->getMessage()
            );
            return [];
        }

        foreach ($calendars as $calendar) {
            $color = '';
            if (method_exists(object_or_class: $calendar, method: 'getDisplayColor') === true) {
                $color = (string) ($calendar->getDisplayColor() ?? '');
            }

            $out[] = [
                'key'   => (string) $calendar->getKey(),
                'name'  => (string) $calendar->getDisplayName(),
                'color' => $color,
            ];
        }

        return $out;
    }//end listCalendars()

    /**
     * Fetch events from internal Nextcloud calendars.
     *
     * Uses OCP\Calendar\IManager::search() when available so ACL is
     * delegated to NC's calendar app. If IManager is unavailable
     * (typical in unit tests or minimal NC installs) returns an empty
     * result without raising.
     *
     * @param array<int, string> $principals Calendar principal/key URIs.
     * @param string             $from       ISO start.
     * @param string             $to         ISO end.
     *
     * @return array{events: array<int, array<string, mixed>>, failures: array<int, string>}
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function fetchInternalEvents(array $principals, string $from, string $to): array
    {
        $events   = [];
        $failures = [];

        if ($this->calendarMgr === null) {
            return [
                'events'   => $events,
                'failures' => $failures,
            ];
        }

        try {
            $startObj = new DateTimeImmutable(datetime: $from);
            $endObj   = new DateTimeImmutable(datetime: $to);
        } catch (Throwable $exception) {
            $this->logger->warning(
                message: 'Calendar widget received malformed from/to: '.$exception->getMessage()
            );
            return [
                'events'   => $events,
                'failures' => ['internal: invalid date range'],
            ];
        }

        try {
            $matches = $this->calendarMgr->search(
                pattern: '',
                searchProperties: [],
                options: [
                    'timerange' => [
                        'start' => $startObj,
                        'end'   => $endObj,
                    ],
                ],
                limit: null,
                offset: null
            );
        } catch (Throwable $exception) {
            $this->logger->warning(
                message: 'Calendar widget internal search failed: '.$exception->getMessage()
            );
            return [
                'events'   => $events,
                'failures' => ['internal: '.$exception->getMessage()],
            ];
        }//end try

        foreach ($matches as $match) {
            $matchArr = (array) $match;

            $calId   = (string) ($matchArr['calendar-key'] ?? $matchArr['calendarUri'] ?? '');
            $calName = (string) ($matchArr['calendar-name'] ?? $matchArr['calendarName'] ?? '');

            if ($principals !== [] && in_array(needle: $calId, haystack: $principals, strict: true) === false
                && in_array(needle: $calName, haystack: $principals, strict: true) === false
            ) {
                // Caller restricted to a subset; skip out-of-scope calendars.
                continue;
            }

            $events[] = $this->normalizeInternalEvent(raw: $matchArr);
        }

        return [
            'events'   => $events,
            'failures' => $failures,
        ];
    }//end fetchInternalEvents()

    /**
     * Fetch events from a list of external ICS URLs.
     *
     * URLs that are non-HTTPS, resolve to private IPs, exceed the size
     * cap, or are not in the (optional) admin allow-list are silently
     * skipped with a logged warning. Each successfully fetched body is
     * cached for the configured TTL and re-parsed on every call.
     *
     * @param array<int, string> $urls External ICS URLs.
     * @param string             $from ISO start.
     * @param string             $to   ISO end.
     *
     * @return array{events: array<int, array<string, mixed>>, failures: array<int, string>}
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function fetchExternalIcsEvents(array $urls, string $from, string $to): array
    {
        $events   = [];
        $failures = [];

        foreach ($urls as $url) {
            $url = (string) $url;

            if ($this->validateUrl(url: $url) === false) {
                $failures[] = 'external: rejected URL '.$url;
                $this->logger->info(message: 'Calendar widget rejected external URL: '.$url);
                continue;
            }

            if ($this->checkAllowList(url: $url) === false) {
                $failures[] = 'external: not in allow-list '.$url;
                $this->logger->info(message: 'Calendar widget URL not in allow-list: '.$url);
                continue;
            }

            try {
                $body = $this->fetchIcsBody(url: $url);
            } catch (Throwable $exception) {
                $failures[] = 'external: fetch failed '.$url;
                $this->logger->warning(
                    message: 'Calendar widget fetch failed for '.$url.': '.$exception->getMessage()
                );
                continue;
            }

            try {
                $expanded = $this->parseAndExpandIcs(body: $body, from: $from, to: $to);
            } catch (Throwable $exception) {
                $failures[] = 'external: parse failed '.$url;
                $this->logger->warning(
                    message: 'Calendar widget parse failed for '.$url.': '.$exception->getMessage()
                );
                continue;
            }

            foreach ($expanded as $event) {
                $events[] = $event;
            }
        }//end foreach

        return [
            'events'   => $events,
            'failures' => $failures,
        ];
    }//end fetchExternalIcsEvents()

    /**
     * Validate an external ICS URL.
     *
     * Delegates to {@see UrlSafetyValidator::isSafe()}: accepts only HTTPS
     * URLs whose hostname resolves exclusively to public IPs.
     *
     * @param string $url The URL to validate.
     *
     * @return bool True when the URL passes all checks.
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function validateUrl(string $url): bool
    {
        return $this->urlValidator->isSafe(url: $url);
    }//end validateUrl()

    /**
     * Check whether a URL's host appears in the admin allow-list.
     *
     * Delegates to {@see UrlSafetyValidator::checkAllowList()}. Empty /
     * missing allow-list means all hosts are allowed. Comparison is
     * case-insensitive and exact (no wildcard subdomain expansion).
     *
     * @param string $url The URL to check.
     *
     * @return bool True when the host passes the allow-list.
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function checkAllowList(string $url): bool
    {
        return $this->urlValidator->checkAllowList(
            url: $url,
            appId: Application::APP_ID,
            configKey: self::CONFIG_KEY_ALLOWED_HOSTS
        );
    }//end checkAllowList()

    /**
     * Fetch the raw ICS body for a URL, using ICache when fresh.
     *
     * @param string $url The URL to fetch.
     *
     * @return string The raw ICS body.
     *
     * @throws \RuntimeException When the response is too large or non-2xx.
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function fetchIcsBody(string $url): string
    {
        $cacheKey = 'ics_'.md5(string: $url);
        $cached   = $this->cache->get(key: $cacheKey);
        if (is_string(value: $cached) === true && $cached !== '') {
            return $cached;
        }

        // M2: re-validate immediately before the HTTP request to close the
        // DNS-rebinding TOCTOU window — an attacker who flips the DNS record
        // after the initial validateUrl call is caught here. A full fix would
        // pin the IP via CURLOPT_RESOLVE, but that requires Guzzle internals.
        // Double-checking is a practical mitigation that limits the attack
        // window to the sub-millisecond gap between this check and the
        // actual TCP connect.
        if ($this->validateUrl(url: $url) === false) {
            throw new RuntimeException(
                message: 'ICS URL failed re-validation (SSRF guard)'
            );
        }

        $client   = $this->clientService->newClient();
        $response = $client->get(
            uri: $url,
            options: [
                'timeout'         => self::FETCH_TIMEOUT_SECONDS,
                'connect_timeout' => self::FETCH_TIMEOUT_SECONDS,
                // H2: disable redirect-following so an attacker cannot
                // chain a public URL to an internal redirect target.
                'allow_redirects' => false,
            ]
        );

        $body = (string) $response->getBody();
        if (strlen(string: $body) > self::MAX_RESPONSE_SIZE_BYTES) {
            throw new RuntimeException(
                message: 'ICS response exceeds 1MB cap'
            );
        }

        $ttl = $this->appConfig->getValueInt(
            app: 'launchpad',
            key: self::CONFIG_KEY_CACHE_TTL,
            default: self::DEFAULT_CACHE_TTL_SECONDS
        );
        if ($ttl <= 0) {
            $ttl = self::DEFAULT_CACHE_TTL_SECONDS;
        }

        $this->cache->set(key: $cacheKey, value: $body, ttl: $ttl);

        return $body;
    }//end fetchIcsBody()

    /**
     * Parse a raw ICS body and expand recurring events into instances.
     *
     * Uses sabre/vobject's bundled `\Sabre\VObject\Reader::read()` and
     * `VCalendar::expand()`. Each VEVENT becomes one event object.
     *
     * @param string $body The raw ICS body.
     * @param string $from ISO start (inclusive).
     * @param string $to   ISO end (inclusive).
     *
     * @return array<int, array<string, mixed>> Normalised event objects.
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function parseAndExpandIcs(string $body, string $from, string $to): array
    {
        if (class_exists(class: \Sabre\VObject\Reader::class) === false) {
            $this->logger->warning(message: 'sabre/vobject is not available; cannot parse ICS');
            return [];
        }

        $startObj = new DateTimeImmutable(datetime: $from);
        $endObj   = new DateTimeImmutable(datetime: $to);

        // Returns a sabre VCalendar component which we expand server-side.
        $vcal = \Sabre\VObject\Reader::read(data: $body);

        try {
            $vcal->expand(start: $startObj, end: $endObj);
        } catch (Throwable $exception) {
            $this->logger->info(
                message: 'sabre/vobject expansion failed: '.$exception->getMessage()
            );
        }

        $events = [];
        if (isset($vcal->VEVENT) === false) {
            return $events;
        }

        foreach ($vcal->VEVENT as $vevent) {
            $events[] = $this->normalizeVevent(vevent: $vevent);
        }

        return $events;
    }//end parseAndExpandIcs()

    /**
     * Normalise a sabre VEVENT into the canonical event object shape.
     *
     * @param object $vevent The sabre VEVENT component.
     *
     * @return array<string, mixed>
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function normalizeVevent(object $vevent): array
    {
        $uid     = (string) ($vevent->UID ?? '');
        $summary = (string) ($vevent->SUMMARY ?? '');
        $loc     = (string) ($vevent->LOCATION ?? '');
        $desc    = (string) ($vevent->DESCRIPTION ?? '');

        $startIso = $this->veventDateToIso(prop: $vevent->DTSTART ?? null);
        $endIso   = $this->veventDateToIso(prop: $vevent->DTEND ?? null);
        $allDay   = $this->veventIsAllDay(prop: $vevent->DTSTART ?? null);

        $title = 'Untitled';
        if ($summary !== '') {
            $title = $summary;
        }

        $location = null;
        if ($loc !== '') {
            $location = $loc;
        }

        $description = null;
        if ($desc !== '') {
            $description = $desc;
        }

        return [
            'uid'          => $uid,
            'title'        => $title,
            'start'        => $startIso,
            'end'          => $endIso,
            'allDay'       => $allDay,
            'location'     => $location,
            'description'  => $description,
            'calendarId'   => '',
            'calendarName' => '',
            'color'        => null,
            'source'       => 'external',
        ];
    }//end normalizeVevent()

    /**
     * Extract an ISO 8601 string from a sabre VEVENT date property.
     *
     * @param object|null $prop The DTSTART/DTEND property (or null).
     *
     * @return string ISO 8601 string or empty string.
     */
    private function veventDateToIso(?object $prop): string
    {
        if ($prop === null) {
            return '';
        }

        try {
            if (method_exists(object_or_class: $prop, method: 'getDateTime') === true) {
                // The DateTime instance returned implements DateTimeInterface.
                $dateTime = $prop->getDateTime();
                return $dateTime->format(format: \DATE_ATOM);
            }
        } catch (Throwable $exception) {
            // Fall through to string cast on any extraction error.
            unset($exception);
        }

        return (string) $prop;
    }//end veventDateToIso()

    /**
     * Detect whether a VEVENT DTSTART represents an all-day event.
     *
     * @param object|null $prop The DTSTART property.
     *
     * @return bool True when VALUE=DATE (no time component).
     */
    private function veventIsAllDay(?object $prop): bool
    {
        if ($prop === null) {
            return false;
        }

        try {
            if (method_exists(object_or_class: $prop, method: 'hasTime') === true) {
                return $prop->hasTime() === false;
            }
        } catch (Throwable $exception) {
            unset($exception);
        }

        return false;
    }//end veventIsAllDay()

    /**
     * Unwrap one property value from an NC CalDAV search result.
     *
     * `CalDavBackend::transformSearchProperty()` stores every property as a
     * `[value, parameters]` pair, and a repeatable property nests one level
     * deeper (`[[value, parameters], …]`). A naive `(string)` cast of either
     * shape yields the literal `"Array"` — the bug this unwraps. Plain scalar
     * inputs (older NC versions / unit-test fixtures) pass straight through.
     *
     * @param mixed $prop The raw property as returned by IManager::search().
     *
     * @return mixed The first scalar/object value, or null when absent.
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    private function extractSearchValue(mixed $prop): mixed
    {
        if (is_array(value: $prop) === false) {
            return $prop;
        }

        $first = ($prop[0] ?? null);
        // Repeatable property: [[value, parameters], …] → unwrap once more.
        if (is_array(value: $first) === true) {
            return ($first[0] ?? null);
        }

        return $first;
    }//end extractSearchValue()

    /**
     * Normalise a NC IManager::search() result into the canonical shape.
     *
     * @param array<string, mixed> $raw Raw event from IManager.
     *
     * @return array<string, mixed>
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    public function normalizeInternalEvent(array $raw): array
    {
        // NC Calendar search results vary across versions; the VEVENT
        // properties live under objects[0], each as a [value, parameters]
        // pair (see extractSearchValue). Fall back to flat keys for other
        // shapes (older NC / test fixtures).
        $obj = ($raw['objects'][0] ?? $raw);

        $titleVal = ($this->extractSearchValue(prop: $raw['SUMMARY'] ?? null)
            ?? $this->extractSearchValue(prop: $obj['SUMMARY'] ?? null)
            ?? ($raw['title'] ?? null));
        $startVal = ($this->extractSearchValue(prop: $raw['DTSTART'] ?? null)
            ?? $this->extractSearchValue(prop: $obj['DTSTART'] ?? null)
            ?? ($raw['start'] ?? null));
        $endVal = ($this->extractSearchValue(prop: $raw['DTEND'] ?? null)
            ?? $this->extractSearchValue(prop: $obj['DTEND'] ?? null)
            ?? ($raw['end'] ?? null));
        $locVal = ($this->extractSearchValue(prop: $raw['LOCATION'] ?? null)
            ?? $this->extractSearchValue(prop: $obj['LOCATION'] ?? null)
            ?? ($raw['location'] ?? null));
        $descVal = ($this->extractSearchValue(prop: $raw['DESCRIPTION'] ?? null)
            ?? $this->extractSearchValue(prop: $obj['DESCRIPTION'] ?? null)
            ?? ($raw['description'] ?? null));
        $uidVal = ($this->extractSearchValue(prop: $raw['UID'] ?? null)
            ?? $this->extractSearchValue(prop: $obj['UID'] ?? null)
            ?? ($raw['uid'] ?? null));

        $title = (string) ($titleVal ?? '');
        $resolvedTitle = ($title !== '') ? $title : 'Untitled';

        $resolvedLocation = ($locVal !== null && $locVal !== '') ? (string) $locVal : null;
        $resolvedDescription = ($descVal !== null && $descVal !== '') ? (string) $descVal : null;

        return [
            'uid'          => (string) ($uidVal ?? ''),
            'title'        => $resolvedTitle,
            'start'        => $this->scalarDateToIso(value: $startVal),
            'end'          => $this->scalarDateToIso(value: $endVal),
            'allDay'       => (bool) ($raw['allDay'] ?? false),
            'location'     => $resolvedLocation,
            'description'  => $resolvedDescription,
            'calendarId'   => (string) ($raw['calendar-key'] ?? $raw['calendarUri'] ?? ''),
            'calendarName' => (string) ($raw['calendar-name'] ?? $raw['calendarName'] ?? ''),
            'color'        => $raw['calendar-color'] ?? null,
            'source'       => 'internal',
        ];
    }//end normalizeInternalEvent()

    /**
     * Coerce a search-result date value to an ISO 8601 string.
     *
     * Expanded recurrences arrive as `\DateTimeInterface`; raw scalars (test
     * fixtures, pre-formatted strings) pass through unchanged.
     *
     * @param mixed $value The DTSTART/DTEND value.
     *
     * @return string The ISO 8601 timestamp, or '' when unset.
     *
     * @spec openspec/specs/calendar-widget/spec.md
     */
    private function scalarDateToIso(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(format: 'c');
        }

        if (is_string(value: $value) === true) {
            return $value;
        }

        return '';
    }//end scalarDateToIso()
}//end class
