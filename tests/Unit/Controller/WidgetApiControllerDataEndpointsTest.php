<?php

/**
 * WidgetApiController Data-Endpoint Contract Test
 *
 * Wire-contract coverage for the three widget data endpoints:
 *
 *   - `GET /api/widgets/news/{placementId}/items`      — newsItems
 *   - `GET /api/widgets/calendar/{placementId}/events` — calendarEvents
 *   - `GET /api/widgets/calendar/calendars`            — calendars
 *
 * All three are `#[NoAdminRequired]` + `#[NoCSRFRequired]` and two of them
 * take a bare integer placement id, so any authenticated account can address
 * any placement in the instance. The contract asserted here is therefore
 * dominated by refusals:
 *
 *   * anonymous → 401 without any outbound fetch;
 *   * ADR-023 action denial → 403 before the placement is read;
 *   * M1: these are DATA-FETCH endpoints, so `canViewPlacement` — not
 *     `canStyleWidget` — is the gate. A VIEW_ONLY user is a legitimate
 *     consumer, and a caller who cannot view MUST get 403 with no HTTP fetch
 *     performed on their behalf (an SSRF-adjacent property: the placement
 *     holds caller-supplied external ICS/RSS URLs).
 *
 * Plus the input validation the endpoints own themselves: the news `limit`
 * clamp range and the calendar mandatory-range / one-year cap (design D1),
 * which is asserted by reading the window actually handed to the service.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use DateTimeImmutable;
use Exception;
use OCA\LaunchPad\Controller\WidgetApiController;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Contract tests for newsItems, calendarEvents and calendars.
 */
class WidgetApiControllerDataEndpointsTest extends TestCase
{

    /**
     * HTTP request mock.
     *
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * Widget service mock.
     *
     * @var WidgetService&MockObject
     */
    private $widgetService;

    /**
     * Permission service mock.
     *
     * @var PermissionService&MockObject
     */
    private $permissionService;

    /**
     * News widget service mock.
     *
     * @var NewsWidgetService&MockObject
     */
    private $newsWidgetService;

    /**
     * Calendar widget service mock.
     *
     * @var CalendarWidgetService&MockObject
     */
    private $calendarService;

    /**
     * ADR-023 action authorization mock.
     *
     * @var ActionAuthService&MockObject
     */
    private $actionAuth;

    /**
     * User session mock.
     *
     * @var IUserSession&MockObject
     */
    private $userSession;


    /**
     * Set up shared mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request           = $this->createMock(IRequest::class);
        $this->widgetService     = $this->createMock(WidgetService::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->newsWidgetService = $this->createMock(NewsWidgetService::class);
        $this->calendarService   = $this->createMock(CalendarWidgetService::class);
        $this->actionAuth        = $this->createMock(ActionAuthService::class);
        $this->userSession       = $this->createMock(IUserSession::class);

    }//end setUp()


    /**
     * Build the controller for the supplied user (NULL = anonymous).
     *
     * @param string|null $userId The acting user ID.
     *
     * @return WidgetApiController
     */
    private function makeController(?string $userId): WidgetApiController
    {
        $user = null;
        if ($userId !== null) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($userId);
        }

        $this->userSession->method('getUser')->willReturn($user);

        return new WidgetApiController(
            request: $this->request,
            widgetService: $this->widgetService,
            permissionService: $this->permissionService,
            newsWidgetService: $this->newsWidgetService,
            calendarService: $this->calendarService,
            placementService: $this->createMock(WidgetPlacementService::class),
            roleFeaturePerm: $this->createMock(RoleFeaturePermissionService::class),
            userSession: $this->userSession,
            actionAuth: $this->actionAuth,
            logger: $this->createMock(LoggerInterface::class),
            userId: $userId,
        );

    }//end makeController()


    /**
     * Build a calendar-widget placement carrying the given content blob.
     *
     * @param array $content The widget content blob.
     *
     * @return WidgetPlacement
     */
    private function makePlacement(array $content): WidgetPlacement
    {
        $placement = new WidgetPlacement();
        $placement->setId(7);
        $placement->setDashboardId(1);
        $placement->setWidgetId('calendar');
        $placement->setContent(json_encode($content));

        return $placement;

    }//end makePlacement()


    // -----------------------------------------------------------------------
    // newsItems — GET /api/widgets/news/{placementId}/items
    // -----------------------------------------------------------------------


    /**
     * An anonymous caller MUST get 401 and MUST NOT cause a feed fetch.
     *
     * @return void
     */
    public function testNewsItemsRejectsAnonymousWith401(): void
    {
        $this->newsWidgetService->expects($this->never())->method('getItemsForPlacement');

        $controller = $this->makeController(null);
        $response   = $controller->newsItems(placementId: 7);

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testNewsItemsRejectsAnonymousWith401()


    /**
     * ADR-023: a denied `widget.news-items` action MUST be 403 before any
     * permission lookup or fetch.
     *
     * @return void
     */
    public function testNewsItemsRejectsDeniedActionWith403(): void
    {
        $this->actionAuth->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $this->permissionService->expects($this->never())->method('canViewPlacement');
        $this->newsWidgetService->expects($this->never())->method('getItemsForPlacement');

        $controller = $this->makeController('alice');
        $response   = $controller->newsItems(placementId: 7);

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );

    }//end testNewsItemsRejectsDeniedActionWith403()


    /**
     * The documented clamp is a REJECTION, not a silent correction: a limit
     * outside [1, 50] MUST be 400 and MUST NOT fetch anything. Asserted at
     * both ends of the range plus the two accepted boundaries, because an
     * off-by-one here is exactly what an inclusive/exclusive mix-up produces.
     *
     * @param integer $limit    The caller-supplied limit.
     * @param boolean $accepted Whether the controller must accept it.
     *
     * @return void
     *
     * @dataProvider limitBoundaryProvider
     */
    public function testNewsItemsEnforcesTheLimitRange(int $limit, bool $accepted): void
    {
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->newsWidgetService->method('getItemsForPlacement')
            ->willReturn(['items' => [], 'feedsFailed' => 0, 'failedUrls' => []]);

        $controller = $this->makeController('alice');
        $response   = $controller->newsItems(placementId: 7, limit: $limit);

        if ($accepted === true) {
            $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
            return;
        }

        $this->assertSame(
            expected: Http::STATUS_BAD_REQUEST,
            actual: $response->getStatus()
        );

    }//end testNewsItemsEnforcesTheLimitRange()


    /**
     * Boundary cases for the news `limit` parameter.
     *
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function limitBoundaryProvider(): array
    {
        return [
            'zero is below the range'   => [0, false],
            'one is the lower bound'    => [1, true],
            'fifty is the upper bound'  => [50, true],
            'fifty-one is above range'  => [51, false],
            'negative is below range'   => [-5, false],
        ];

    }//end limitBoundaryProvider()


    /**
     * M1 / REQ-PERM-001: a caller who cannot VIEW the placement gets 403 and
     * — the property that matters — no feed is fetched on their behalf. The
     * placement holds caller-supplied external URLs, so a fetch performed
     * before the check would be a request the server makes for an
     * unauthorised party.
     *
     * @return void
     */
    public function testNewsItemsRefusesANonViewerWithoutFetching(): void
    {
        $this->permissionService->expects($this->once())
            ->method('canViewPlacement')
            ->with(userId: 'mallory', placementId: 7)
            ->willReturn(false);

        $this->newsWidgetService->expects($this->never())->method('getItemsForPlacement');

        $controller = $this->makeController('mallory');
        $response   = $controller->newsItems(placementId: 7, limit: 10);

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );

    }//end testNewsItemsRefusesANonViewerWithoutFetching()


    /**
     * REQ-NEWS-003: the success envelope is the service payload verbatim —
     * items plus the partial-failure counters the widget renders as a
     * degraded state rather than an error.
     *
     * @return void
     */
    public function testNewsItemsReturnsTheServicePayloadIncludingFailureCounters(): void
    {
        $this->permissionService->method('canViewPlacement')->willReturn(true);

        $payload = [
            'items'       => [['title' => 'Release 2.2.0']],
            'feedsFailed' => 1,
            'failedUrls'  => ['https://example.invalid/rss'],
        ];

        $this->newsWidgetService->expects($this->once())
            ->method('getItemsForPlacement')
            ->with(placementId: 7, limit: 25)
            ->willReturn($payload);

        $controller = $this->makeController('alice');
        $response   = $controller->newsItems(placementId: 7, limit: 25);

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: $payload, actual: $response->getData());

    }//end testNewsItemsReturnsTheServicePayloadIncludingFailureCounters()


    /**
     * A NULL limit is the documented default of 10, not a rejection.
     *
     * @return void
     */
    public function testNewsItemsDefaultsANullLimitToTen(): void
    {
        $this->permissionService->method('canViewPlacement')->willReturn(true);

        $this->newsWidgetService->expects($this->once())
            ->method('getItemsForPlacement')
            ->with(placementId: 7, limit: 10)
            ->willReturn(['items' => [], 'feedsFailed' => 0, 'failedUrls' => []]);

        $controller = $this->makeController('alice');
        $response   = $controller->newsItems(placementId: 7, limit: null);

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

    }//end testNewsItemsDefaultsANullLimitToTen()


    // -----------------------------------------------------------------------
    // calendarEvents — GET /api/widgets/calendar/{placementId}/events
    // -----------------------------------------------------------------------


    /**
     * An anonymous caller MUST get 401 and MUST NOT reach the placement.
     *
     * @return void
     */
    public function testCalendarEventsRejectsAnonymousWith401(): void
    {
        $this->widgetService->expects($this->never())->method('getPlacement');

        $controller = $this->makeController(null);
        $response   = $controller->calendarEvents(
            placementId: 7,
            from: '2026-01-01T00:00:00+00:00',
            to: '2026-01-31T00:00:00+00:00'
        );

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testCalendarEventsRejectsAnonymousWith401()


    /**
     * The date range is MANDATORY: an omitted bound is 400, and no placement
     * is read.
     *
     * @return void
     */
    public function testCalendarEventsRequiresBothRangeBounds(): void
    {
        $this->widgetService->expects($this->never())->method('getPlacement');

        $controller = $this->makeController('alice');
        $response   = $controller->calendarEvents(
            placementId: 7,
            from: '2026-01-01T00:00:00+00:00',
            to: ''
        );

        $this->assertSame(
            expected: Http::STATUS_BAD_REQUEST,
            actual: $response->getStatus()
        );

    }//end testCalendarEventsRequiresBothRangeBounds()


    /**
     * An inverted range is 400 — `to` before `from` is a client bug, not an
     * empty result.
     *
     * @return void
     */
    public function testCalendarEventsRejectsAnInvertedRange(): void
    {
        $this->widgetService->expects($this->never())->method('getPlacement');

        $controller = $this->makeController('alice');
        $response   = $controller->calendarEvents(
            placementId: 7,
            from: '2026-06-01T00:00:00+00:00',
            to: '2026-01-01T00:00:00+00:00'
        );

        $this->assertSame(
            expected: Http::STATUS_BAD_REQUEST,
            actual: $response->getStatus()
        );

    }//end testCalendarEventsRejectsAnInvertedRange()


    /**
     * Design D1: a window wider than a year is CAPPED, not rejected. The cap
     * is asserted on the value actually handed to the service — a cap that
     * is computed and then not applied looks identical from the status code.
     *
     * @return void
     */
    public function testCalendarEventsCapsTheWindowAtOneYear(): void
    {
        $this->widgetService->method('getPlacement')
            ->willReturn($this->makePlacement(content: ['internalCalendars' => ['personal']]));
        $this->permissionService->method('canViewPlacement')->willReturn(true);

        $capturedTo = null;
        $this->calendarService->expects($this->once())
            ->method('getEvents')
            ->willReturnCallback(
                function (array $config, string $from, string $to) use (&$capturedTo): array {
                    unset($config, $from);
                    $capturedTo = $to;
                    return ['events' => []];
                }
            );

        $controller = $this->makeController('alice');
        $response   = $controller->calendarEvents(
            placementId: 7,
            from: '2026-01-01T00:00:00+00:00',
            to: '2030-01-01T00:00:00+00:00'
        );

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(
            expected: '2027-01-01',
            actual: (new DateTimeImmutable($capturedTo))->format('Y-m-d')
        );

    }//end testCalendarEventsCapsTheWindowAtOneYear()


    /**
     * An unknown placement is 404, and no calendar is ever queried.
     *
     * @return void
     */
    public function testCalendarEventsReturns404ForAnUnknownPlacement(): void
    {
        $this->widgetService->method('getPlacement')
            ->willThrowException(new Exception('not found'));

        $this->calendarService->expects($this->never())->method('getEvents');

        $controller = $this->makeController('alice');
        $response   = $controller->calendarEvents(
            placementId: 404,
            from: '2026-01-01T00:00:00+00:00',
            to: '2026-01-31T00:00:00+00:00'
        );

        $this->assertSame(
            expected: Http::STATUS_NOT_FOUND,
            actual: $response->getStatus()
        );

    }//end testCalendarEventsReturns404ForAnUnknownPlacement()


    /**
     * M1: a caller who cannot view the placement gets 403 and no external
     * ICS URL configured on that placement is fetched for them.
     *
     * @return void
     */
    public function testCalendarEventsRefusesANonViewerWithoutFetching(): void
    {
        $this->widgetService->method('getPlacement')
            ->willReturn(
                $this->makePlacement(
                    content: ['externalIcsUrls' => ['https://internal.invalid/private.ics']]
                )
            );

        $this->permissionService->expects($this->once())
            ->method('canViewPlacement')
            ->with(userId: 'mallory', placementId: 7)
            ->willReturn(false);

        $this->calendarService->expects($this->never())->method('getEvents');

        $controller = $this->makeController('mallory');
        $response   = $controller->calendarEvents(
            placementId: 7,
            from: '2026-01-01T00:00:00+00:00',
            to: '2026-01-31T00:00:00+00:00'
        );

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );

    }//end testCalendarEventsRefusesANonViewerWithoutFetching()


    /**
     * REQ-CAL-003: the placement's stored config reaches the aggregator —
     * internal calendar keys and external ICS URLs both — and the aggregated
     * payload comes back as a 200 body.
     *
     * @return void
     */
    public function testCalendarEventsPassesThePlacementConfigAndReturnsEvents(): void
    {
        $this->widgetService->method('getPlacement')
            ->willReturn(
                $this->makePlacement(
                    content: [
                        'internalCalendars' => ['personal', 'work'],
                        'externalIcsUrls'   => ['https://example.test/team.ics'],
                        'viewMode'          => 'month',
                        'daysAhead'         => 30,
                    ]
                )
            );
        $this->permissionService->method('canViewPlacement')->willReturn(true);

        $capturedConfig = null;
        $this->calendarService->expects($this->once())
            ->method('getEvents')
            ->willReturnCallback(
                function (array $config, string $from, string $to) use (&$capturedConfig): array {
                    unset($from, $to);
                    $capturedConfig = $config;
                    return ['events' => [['summary' => 'Standup']]];
                }
            );

        $controller = $this->makeController('alice');
        $response   = $controller->calendarEvents(
            placementId: 7,
            from: '2026-01-01T00:00:00+00:00',
            to: '2026-01-31T00:00:00+00:00'
        );

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(
            expected: ['personal', 'work'],
            actual: $capturedConfig['internalCalendars']
        );
        $this->assertSame(
            expected: ['https://example.test/team.ics'],
            actual: $capturedConfig['externalIcsUrls']
        );
        $this->assertSame(expected: 'month', actual: $capturedConfig['viewMode']);
        $this->assertSame(expected: 30, actual: $capturedConfig['daysAhead']);
        $this->assertSame(
            expected: [['summary' => 'Standup']],
            actual: $response->getData()['events']
        );

    }//end testCalendarEventsPassesThePlacementConfigAndReturnsEvents()


    // -----------------------------------------------------------------------
    // calendars — GET /api/widgets/calendar/calendars
    // -----------------------------------------------------------------------


    /**
     * An anonymous caller MUST get 401 and MUST NOT enumerate calendars.
     *
     * @return void
     */
    public function testCalendarsRejectsAnonymousWith401(): void
    {
        $this->calendarService->expects($this->never())->method('listCalendars');

        $controller = $this->makeController(null);
        $response   = $controller->calendars();

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testCalendarsRejectsAnonymousWith401()


    /**
     * ADR-023: a denied action MUST be 403 before any enumeration.
     *
     * @return void
     */
    public function testCalendarsRejectsDeniedActionWith403(): void
    {
        $this->actionAuth->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $this->calendarService->expects($this->never())->method('listCalendars');

        $controller = $this->makeController('alice');
        $response   = $controller->calendars();

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );

    }//end testCalendarsRejectsDeniedActionWith403()


    /**
     * REQ-CAL-002: the picker is scoped to the CALLER — the uid handed to
     * the service is the session's, never a request parameter — and the
     * envelope nests the list under `calendars`.
     *
     * @return void
     */
    public function testCalendarsListsOnlyTheCallersOwnCalendars(): void
    {
        $calendars = [
            ['key' => 'personal', 'displayName' => 'Personal', 'color' => '#0082c9'],
        ];

        $this->calendarService->expects($this->once())
            ->method('listCalendars')
            ->with(userId: 'alice')
            ->willReturn($calendars);

        $controller = $this->makeController('alice');
        $response   = $controller->calendars();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: $calendars, actual: $response->getData()['calendars']);

    }//end testCalendarsListsOnlyTheCallersOwnCalendars()


    /**
     * ADR-005: a backend failure while enumerating MUST NOT leak its message
     * — the caller sees the generic envelope.
     *
     * @return void
     */
    public function testCalendarsHidesBackendFailureDetail(): void
    {
        $this->calendarService->method('listCalendars')
            ->willThrowException(new Exception('CalDAV principal /principals/users/alice unreachable'));

        $controller = $this->makeController('alice');
        $response   = $controller->calendars();

        $this->assertNotSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertStringNotContainsString(
            needle: 'principals',
            haystack: json_encode($response->getData())
        );

    }//end testCalendarsHidesBackendFailureDetail()


}//end class
