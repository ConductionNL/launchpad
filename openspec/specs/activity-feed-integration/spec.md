---
status: done
---

# Activity Feed Integration Specification

## Purpose

Surface LaunchPad events in Nextcloud's standard Activity feed so every action on a dashboard — creation, editing, publication, sharing, commenting, locking, and role changes — is visible to the relevant users in their NC notifications and activity stream. This capability defines the NC Activity extension class, all event-type constants, audience-targeting rules, debounce logic, subject/message templates, icon conventions, the cross-capability emission contract, and the unit-test contract. Actual `publishActivity()` call-sites are delegated to the sibling capability that owns each action.

## Data Model

No new database tables. NC Activity persists events in the existing `oc_activity` table (managed by NC core). Each LaunchPad event row carries:

- **app**: `'launchpad'`
- **type**: one of the 13 `Extension::EVENT_*` constants
- **user**: the NC user ID of the recipient
- **affecteduser**: the NC user ID of the actor
- **object_type**: `'launchpad_dashboard'`
- **object_id**: the dashboard UUID (string)
- **subject**: translated template key
- **subjectparams**: JSON-encoded parameter array consumed by NC Activity's translation layer
- **message**: optional detail line (comment body excerpt for `dashboard_commented`)
- **link**: full NC URL to the dashboard
- **timestamp**: Unix epoch of the event

## Requirements

### Requirement: REQ-ACT-001 Extension Registration

The system MUST register a class `\OCA\LaunchPad\Activity\Extension` that implements `OCP\Activity\IProvider` and is declared in `appinfo/info.xml` under the `<activity>` section with application id `launchpad`. The extension MUST be resolvable from the NC DI container at runtime.

#### Scenario: Extension resolves from DI container

- GIVEN LaunchPad is installed and enabled
- WHEN NC Activity attempts to resolve the registered provider via the DI container
- THEN it MUST receive an instance of `\OCA\LaunchPad\Activity\Extension` without error

#### Scenario: Extension is listed in NC Activity admin settings

- GIVEN LaunchPad is installed
- WHEN an administrator opens NC Settings → Activity
- THEN the LaunchPad extension MUST appear as a registered application with its 13 event types visible for per-type opt-out configuration

#### Scenario: Unknown event type does not throw

@e2e exclude no endpoint can hand parse() a LaunchPad event of an unknown type, so there is no HTTP outcome. The product does the OPPOSITE of this scenario: it throws UnknownActivityException, NC's provider-chain contract, pinned by tests/Unit/Activity/ExtensionTest.php::testParseThrowsForUnknownType

- GIVEN `Extension::parse()` receives an `IEvent` whose `type` is not one of the 13 known `EVENT_*` constants
- WHEN `parse()` is called
- THEN it MUST return the unmodified `IEvent` without throwing any exception

#### Scenario: `getIcon()` returns non-empty URL for all known types

- GIVEN any of the 13 known event type strings is passed to `Extension::getIcon()`
- WHEN the method executes
- THEN it MUST return a non-empty absolute URL string pointing to an SVG resource served by LaunchPad

#### Scenario: `getIcon()` falls back for unknown types

@e2e exclude parse() only calls getIcon() with a known type, so an unknown type cannot reach it over HTTP; the fallback to launchpad.svg is pinned by tests/Unit/Activity/ExtensionTest.php::testGetIconReturnsNonEmptyForAllTypes

- GIVEN an unknown event type string is passed to `Extension::getIcon()`
- WHEN the method executes
- THEN it MUST return the URL to the generic LaunchPad activity icon (`img/activity/launchpad.svg`)

### Requirement: REQ-ACT-002 Event-Type Catalogue

The system MUST define exactly 13 event-type constants on `\OCA\LaunchPad\Activity\Extension`, grouped in a static `ALL_EVENTS` array, covering the full set of trackable LaunchPad actions. No event type may be emitted by `ActivityPublisher` unless it is declared in `ALL_EVENTS`.

NOTE: The 13 types split into two groups by origin. Five are direct ports of source-application event types, renamed to the `dashboard_*` prefix: `dashboard_created`, `dashboard_updated`, `dashboard_deleted`, `dashboard_commented`, `dashboard_reacted`. Eight are LaunchPad-native additions that emerge from sibling capabilities with no source-app counterpart: `dashboard_published`, `dashboard_unpublished`, `dashboard_scheduled` (from `dashboard-draft-published`), `dashboard_shared` (from `dashboard-sharing`), `dashboard_public_share_created` (from `dashboard-public-share`), `dashboard_restored` (from `dashboard-versioning`), `dashboard_lock_overridden` (from `dashboard-locking`), `dashboard_role_changed` (from `admin-roles`). The catalogue is intentionally larger than the source; the additions are deliberate product extensions, not gaps or errors.

NOTE: `dashboard_viewed` is NOT in the catalogue and MUST NOT be added. View tracking is owned exclusively by the `dashboard-view-analytics` capability. Publishing view events to the Activity stream would flood notification inboxes on widely-shared dashboards with passive, non-actionable data.

#### Scenario: All 13 constants present and non-empty

@e2e exclude a PHP class-constant property with no HTTP surface; pinned by tests/Unit/Activity/ExtensionTest.php::testAllEventsHasThirteenUniqueValues, which asserts 14 values (dashboard_acknowledged was added), so "exactly these 13" is stale

- GIVEN `Extension::ALL_EVENTS` is inspected at runtime
- THEN it MUST contain exactly these 13 string values (in any order): `dashboard_created`, `dashboard_updated`, `dashboard_deleted`, `dashboard_published`, `dashboard_unpublished`, `dashboard_scheduled`, `dashboard_shared`, `dashboard_public_share_created`, `dashboard_commented`, `dashboard_reacted`, `dashboard_restored`, `dashboard_lock_overridden`, `dashboard_role_changed`
- AND no value MUST be empty or duplicated

#### Scenario: Unknown type rejected by `ActivityPublisher`

@e2e exclude no endpoint passes an arbitrary type to ActivityPublisher::publish(); pinned by tests/Unit/Activity/ExtensionTest.php::testUnknownTypeDropped and the "publish unknown type" case of tests/Unit/Activity/ActivityPublisherGoldenTest.php::testRowsMatchTheRecordedFixture (warning logged, no row)

- GIVEN `ActivityPublisher::publish()` is called with an event type string not present in `Extension::ALL_EVENTS`
- WHEN `publish()` executes
- THEN it MUST log a WARNING and return without calling `IManager::publishActivity()`
- AND no row MUST be written to `oc_activity`

#### Scenario: Constants are importable from sibling-capability files

@e2e exclude PHP autoloading of a public class constant, no runtime outcome; exercised by every class that compiles against it (lib/Service/AcknowledgementService.php, lib/Activity/DashboardActivityEmitter.php) under tests/Unit/Activity/DashboardActivityEmissionTest.php

- GIVEN a sibling capability (e.g. `dashboard-comments`) needs to reference the comment event type
- WHEN it uses `Extension::EVENT_COMMENTED`
- THEN PHP MUST resolve the constant without an autoload error
- NOTE: Constants MUST be public; no dynamic-only resolution pattern is acceptable

#### Scenario: `ALL_EVENTS` is iterable for registration loop

@e2e exclude internal registration wiring with no HTTP outcome, and not how the product registers: appinfo/info.xml lists four <setting> classes and lib/ has no IManager::registerExtension() loop. What is registered is pinned by tests/Unit/Activity/Settings/ActivitySettingsTest.php::testInfoXmlRegistersEverySetting; the visible listing is the e2e for extension-is-listed-in-nc-activity-admin-settings

- GIVEN NC Activity settings-screen registration code iterates `Extension::ALL_EVENTS`
- WHEN it calls `IManager::registerExtension()` inside the loop
- THEN all 13 types MUST be registered with NC in a single loop without manual repetition

### Requirement: REQ-ACT-003 `publishActivity` Contract

The system MUST provide `\OCA\LaunchPad\Activity\ActivityPublisher` as the sole entry point for emitting LaunchPad activity events. It MUST delegate to `OCP\Activity\IManager::publishActivity()` and MUST NOT write to `oc_activity` directly. All fields required by NC Activity (`app`, `type`, `author`, `affecteduser`, `object_type`, `object_id`, `subject`, `message`, `link`, `timestamp`) MUST be set before the call.

#### Scenario: Correct fields populated on IEvent

- GIVEN `ActivityPublisher::publish('dashboard_updated', 'alice', 'uuid-1', 'Marketing', 'https://nc.example/apps/launchpad#uuid-1')` is called
- WHEN `IManager::publishActivity()` is invoked internally
- THEN the `IEvent` passed to it MUST have: `app = 'launchpad'`, `type = 'dashboard_updated'`, `object_type = 'launchpad_dashboard'`, `object_id = 'uuid-1'`, `link = 'https://nc.example/apps/launchpad#uuid-1'`

#### Scenario: ActivityPublisher never uses static IManager access

@e2e exclude a source-code property (constructor injection), no runtime outcome; no test pins it. Checked by grep: lib/Activity/ contains no Server::get or getActivityManager

- GIVEN the `ActivityPublisher` source is inspected
- THEN it MUST NOT contain any `\OC::$server->getActivityManager()` or `Server::get(IManager::class)` calls
- AND `IManager` MUST be injected via constructor

#### Scenario: Activity failure does not roll back primary action

@e2e exclude needs IManager::publish() to throw, which a test cannot make a running instance do; pinned by the "publish failing manager" case of tests/Unit/Activity/ActivityPublisherGoldenTest.php::testRowsMatchTheRecordedFixture and by tests/Unit/Activity/DashboardActivityEmitterTest.php::testSharedSwallowsAndLogsAPublisherFailure

- GIVEN `IManager::publishActivity()` throws a `\RuntimeException`
- WHEN `ActivityPublisher::publish()` handles the exception
- THEN it MUST log the error and return without re-throwing
- AND the caller (sibling-capability code) MUST continue normally

#### Scenario: `publish()` called after domain action is persisted

- GIVEN a dashboard is updated and the mapper has called `flush()`
- WHEN the service method calls `ActivityPublisher::publish()` after the mapper call
- THEN the activity row MUST reflect the post-update state
- NOTE: Calls before `flush()` or inside a transaction that rolls back violate this contract

### Requirement: REQ-ACT-004 Audience Targeting — Personal Dashboards

A save of a private dashboard (a `user`-type dashboard with no shares and no target groups) MUST NOT write any activity row, not even for its owner (Ruben, 21 Nov 2026; this matches REQ-DIGE-003). Other events on a `user`-type dashboard MUST emit exactly one activity row per event occurrence, addressed to the dashboard owner.

NOTE: REQ-ACT-004 through REQ-ACT-006 and REQ-ACT-008 together implement the normative four-scope audience rule. The recipient set for any event is determined by the dashboard's scope at emit-time:

| Scope | Recipients |
|---|---|
| `user` (personal) | Owner only; a save of a private dashboard writes no row |
| User-to-user share | Owner + every named share recipient |
| `group_shared` | All current members of the target group (resolved via `IGroupManager` at emit-time) |
| Default-group (`groupId = 'default'`) | All authenticated NC users (via `IUserManager::callForAllUsers`), subject to debounce (see REQ-ACT-008) |

NOTE: Group membership MUST be resolved at emit-time, not at read-time. This matches NC core's own pattern and keeps `oc_activity` queries simple. For personal-scope events, the guard on `dashboard->getType()` MUST prevent any `IGroupManager` call.

#### Scenario: Self-action on personal dashboard writes no row

- GIVEN user "alice" has a personal dashboard "Work" that is not shared and has no target groups
- WHEN she saves it
- THEN no `dashboard_updated` row MUST be written to `oc_activity`, not for "alice" and not for anyone else

#### Scenario: Admin action on personal-origin dashboard — single row to owner

@e2e exclude never built: no code path in lib/ emits dashboard_deleted (grep `Extension::EVENT_DELETED` in lib/ finds only the catalogue in lib/Activity/Extension.php), so there is no action to drive and no row to read; no test exists

- GIVEN admin "root" deletes user "bob"'s personal dashboard on his behalf
- WHEN `ActivityPublisher::publish('dashboard_deleted', 'root', uuid, ...)` is called targeting bob's dashboard
- THEN exactly one row MUST be written with `user = 'bob'` and `affecteduser = 'root'`

#### Scenario: No fan-out for personal dashboards

- GIVEN "alice"'s personal dashboard is not shared with anyone
- WHEN she creates a new widget on it
- THEN `oc_activity` MUST contain no new `dashboard_updated` row for this dashboard
- AND no rows MUST be written for any other user

#### Scenario: First-person vs third-person subject selection

@e2e exclude its second GIVEN cannot be reached: no endpoint lets another user update a personal dashboard (DashboardService::updateDashboard throws "Access denied" for a non-owner, lib/Service/DashboardService.php:972). Variant selection is pinned by tests/Unit/Activity/ActivityRowTest.php::testTheActorGetsTheSelfVariant and ::testAnotherUserGetsTheOtherVariant

- GIVEN "alice" acts on her own personal dashboard in a way that writes a row (for example publishing it)
- THEN the activity row MUST use the first-person subject template for that event
- GIVEN "root" acts on "alice"'s personal dashboard
- THEN the activity row addressed to "alice" MUST use the third-person template with actor param `'root'`

### Requirement: REQ-ACT-005 Audience Targeting — Shared Dashboards

For `dashboard_shared` events (shares via the `dashboard-sharing` capability), one share action MUST write one activity row for the sharer that names every recipient of that action, plus one row for each recipient (Ruben, 21 Nov 2026). A group share counts every current member of the group as a recipient; the sharer's row names the group. The sharer never gets a second row as a recipient.

#### Scenario: Share with two users produces three rows

- GIVEN "alice" shares dashboard "Marketing" with "bob" and "carol"
- WHEN `ActivityPublisher::publishToRecipients('dashboard_shared', 'alice', uuid, 'Marketing', link, ['bob', 'carol'])` is called
- THEN `oc_activity` MUST contain three new rows: one for "alice", one for "bob", one for "carol"

#### Scenario: Replacing the share list tells the sharer once

@e2e exclude covered by the share-with-two-users-produces-three-rows e2e, which saves both shares in one replace call and asserts one row for the sharer; the merged recipient list is pinned by tests/Unit/Activity/DashboardActivityEmissionTest.php::testReplacingSharesTellsTheSharerOnceNamingEveryRecipient

- GIVEN "alice" saves the share list of "Marketing" with new shares for "bob" and group "staff" in one request
- WHEN the shares are written
- THEN "alice" MUST get exactly one `dashboard_shared` row, naming "bob, staff"
- AND "bob" and every member of "staff" other than "alice" MUST get one row each

#### Scenario: Actor row uses first-person subject; recipient rows use second/third-person

- GIVEN the same share event above
- THEN "alice"'s row MUST use `"You shared dashboard Marketing with bob, carol"` (first-person)
- AND "bob"'s row MUST use `"alice shared dashboard Marketing with you"` (second-person variant)

#### Scenario: Public share creation — owner and actor only

@e2e exclude never built: no code path in lib/ emits dashboard_public_share_created (grep `Extension::EVENT_PUBLIC_SHARE_CREATED` in lib/ finds only the catalogue in lib/Activity/Extension.php), so there is no action to drive and no row to read; no test exists

- GIVEN admin "root" creates a public share link for "alice"'s dashboard "Marketing"
- WHEN `ActivityPublisher::publish('dashboard_public_share_created', 'root', uuid, 'Marketing', link)` is called
- THEN exactly two rows MUST be written: one for "root", one for "alice" (dashboard owner)
- AND no additional rows MUST be written (no named recipients exist)

#### Scenario: Sharing with a user who already has the dashboard does not skip their row

- GIVEN "carol" already sees the dashboard via a group share
- WHEN "alice" also creates a direct user-to-user share with "carol"
- THEN a `dashboard_shared` activity row MUST still be emitted to "carol"
- NOTE: The activity event is informational — deduplication of shares is handled by the `dashboard-sharing` capability, not Activity

### Requirement: REQ-ACT-006 Audience Targeting — Group-Shared Dashboards

For events on `group_shared`-type dashboards, the system MUST emit one activity row per current member of the target group, resolved at emit-time via `IGroupManager`. The actor receives their own row and MUST NOT receive a duplicate.

#### Scenario: Update on group-shared dashboard fans out to all members

- GIVEN group "marketing" has members ["alice", "bob", "carol"] and admin "root" updates the group-shared dashboard "Campaigns"
- WHEN `ActivityPublisher::publish('dashboard_updated', 'root', uuid, 'Campaigns', link)` is called with the dashboard entity
- THEN `oc_activity` MUST contain exactly 4 new rows: one for each of "root", "alice", "bob", "carol"

#### Scenario: Actor who is also a group member does not receive a duplicate row

- GIVEN admin "root" is also a member of group "marketing" (which has 3 members total including root)
- WHEN "root" updates the group-shared "Campaigns" dashboard
- THEN `oc_activity` MUST contain exactly 3 rows (not 4): one per unique user

#### Scenario: `IGroupManager` is not invoked for personal-dashboard events

@e2e exclude whether a PHP collaborator is called is internal; pinned by tests/Unit/Activity/DashboardActivityEmissionTest.php::testSavingAPrivateDashboardSendsNothing (publishToGroup never called)

- GIVEN a `user`-type dashboard event is being published
- WHEN `ActivityPublisher::publish()` runs
- THEN `IGroupManager` MUST NOT be called
- NOTE: Personal-dashboard audience resolution is always "owner only" — group lookup is guarded by `dashboard->getType() === 'group_shared'`

#### Scenario: Group with zero members produces zero activity rows

- GIVEN group "empty-team" has no members and a group-shared dashboard exists for it
- WHEN an update event is published for that dashboard
- THEN `oc_activity` MUST contain zero new rows for this event
- AND no exception MUST be thrown

### Requirement: REQ-ACT-007 Debounce — Reaction Events

@e2e exclude never built: no code path in lib/ emits dashboard_reacted (grep `Extension::EVENT_REACTED` in lib/ finds only the catalogue and the debounce guard in lib/Activity/ActivityPublisher.php), so there is no action to drive and no row to read. The debounce itself is pinned by tests/Unit/Activity/ExtensionTest.php::testReactionDebounceSuppressesSecondEmission, ::testReactionDebounceIsPerActorPerDashboard and tests/Unit/Activity/DebounceHelperClockTest.php::testReactionClaimExpiresAfterTheTtl

The system MUST apply a debounce per `(actorUserId, dashboardUuid)` pair to `dashboard_reacted` events using an APCu key with a TTL of exactly 900 seconds (15 minutes). At most one reaction activity row per actor per dashboard per 900-second window MUST be written.

#### Scenario: First reaction within window is published

- GIVEN user "bob" reacts to dashboard "Marketing" (uuid `abc`)
- AND no prior reaction from "bob" to `abc` exists in the current APCu window
- WHEN `ActivityPublisher::publish('dashboard_reacted', 'bob', 'abc', ...)` is called
- THEN `allowReaction('bob', 'abc')` MUST return true
- AND one row MUST be written to `oc_activity`

#### Scenario: Second reaction within 15 minutes is suppressed

- GIVEN "bob" already reacted to dashboard `abc` 5 minutes ago (APCu key is live)
- WHEN `ActivityPublisher::publish('dashboard_reacted', 'bob', 'abc', ...)` is called again
- THEN `allowReaction('bob', 'abc')` MUST return false
- AND `IManager::publishActivity()` MUST NOT be called
- AND zero new rows MUST be written to `oc_activity`

#### Scenario: Reaction from a different user is not suppressed by another user's debounce

- GIVEN "bob" reacted to dashboard `abc` 5 minutes ago
- WHEN "carol" reacts to the same dashboard `abc`
- THEN `allowReaction('carol', 'abc')` MUST return true (separate APCu key)
- AND one row MUST be written for "carol"

#### Scenario: Debounce TTL expires and next reaction is allowed

- GIVEN "bob" reacted to dashboard `abc` and the APCu key `launchpad_act_react_bob_abc` has expired (TTL 900 s elapsed)
- WHEN "bob" reacts again
- THEN `allowReaction('bob', 'abc')` MUST return true
- AND one row MUST be written

### Requirement: REQ-ACT-008 Debounce — Global (Default-Group) Fan-Out

For events on dashboards with `groupId = 'default'`, the system MUST apply a debounce per `(dashboardUuid, eventType)` using an APCu key with a TTL of exactly 900 seconds (15 minutes) before performing the full user-enumeration fan-out. This debounce applies to high-frequency types (`dashboard_updated`, `dashboard_commented`). When the debounce window is active, the entire fan-out MUST be skipped silently (no rows written, no exception).

#### Scenario: First global event triggers full fan-out

- GIVEN a default-group dashboard `D-default` is updated
- AND no prior debounce key exists for `(D-default.uuid, 'dashboard_updated')`
- WHEN `ActivityPublisher::publish('dashboard_updated', 'root', D-default.uuid, ...)` is called
- THEN `allowGlobalFanout(D-default.uuid, 'dashboard_updated')` MUST return true
- AND one row per NC user MUST be written to `oc_activity`

#### Scenario: Subsequent global event within 15 minutes is fully suppressed

@e2e exclude its THEN clauses are internal (allowGlobalFanout() returns false, callForAllUsers() is not called) and zero rows alone cannot tell them apart from the daily update debounce that also applies; pinned by tests/Unit/Activity/ExtensionTest.php::testGlobalFanoutIsDebounced (callForAllUsers expected once)

- GIVEN the debounce key for `(D-default.uuid, 'dashboard_updated')` is active
- WHEN another update to the same dashboard triggers `publish('dashboard_updated', ...)` again
- THEN `allowGlobalFanout()` MUST return false
- AND `IUserManager::callForAllUsers()` MUST NOT be called
- AND zero rows MUST be written

#### Scenario: Different event type on same dashboard is not suppressed

- GIVEN debounce for `(D-default.uuid, 'dashboard_updated')` is active
- WHEN `publish('dashboard_published', ...)` is called for the same dashboard
- THEN `allowGlobalFanout(D-default.uuid, 'dashboard_published')` MUST return true (separate key)
- AND the publication fan-out MUST proceed

#### Scenario: DEBUG log entry emitted on suppression

@e2e exclude a DEBUG log line: the instance logs at WARNING and has no API to read the log. The suppression is pinned by tests/Unit/Activity/ExtensionTest.php::testGlobalFanoutIsDebounced; no test asserts the DEBUG message itself

- GIVEN the global debounce is active for an event
- WHEN the fan-out is suppressed
- THEN `ActivityPublisher` MUST emit a DEBUG-level log entry stating that the global fan-out was debounced, including the dashboard UUID and event type
- AND no WARNING or ERROR MUST be logged (suppression is expected behaviour)

### Requirement: REQ-ACT-009 User Opt-Out via NC Settings

The system MUST register its event types with NC Activity's per-type preferences. As in Nextcloud's own Activity app, every event MUST be written to the activity stream, and opting out MUST control only mail and push (Ruben, 21 Nov 2026). Each LaunchPad setting MUST NOT offer a stream switch (`canChangeStream()` is false), MUST offer mail and push switches, and push MUST default to the same value as mail. No custom LaunchPad preferences page is required.

#### Scenario: Default notification preferences for a new user

- GIVEN a new NC user "frank" with no prior Activity preferences
- WHEN LaunchPad emits any activity event addressed to "frank"
- THEN the row MUST be written to his activity stream
- AND mail and push MUST be on for `dashboard_shared` and `dashboard_published` and off for `dashboard_updated` and `dashboard_acknowledged`

#### Scenario: User turns off push for `dashboard_shared`

@e2e exclude whether NC Activity pushes a notification for a row is decided inside the Activity app, and the CI fixture has no push sink; the switch itself is pinned by tests/Unit/Activity/Settings/ActivitySettingsTest.php::testDefaultsAndGroup (canChangeNotification true, canChangeStream false)

- GIVEN "frank" has turned off push for `dashboard_shared` in NC Settings → Activity
- WHEN a dashboard is shared with him
- THEN the `dashboard_shared` row MUST still be written to his activity stream
- AND NC Activity MUST NOT push it to him

#### Scenario: User enables email for `dashboard_shared`

@e2e exclude needs NC Activity's mail cron job and a mail sink, and the CI fixture has neither (no mail catcher in .github/workflows or tests/e2e). The dashboard_shared mail setting is pinned by tests/Unit/Activity/Settings/ActivitySettingsTest.php::testDefaultsAndGroup

- GIVEN "frank" has enabled email delivery for `dashboard_shared`
- WHEN a dashboard is shared with him
- THEN NC's Activity email job MUST include this event in "frank"'s next digest
- NOTE: Email sending is NC's responsibility; LaunchPad only controls whether the row is written with the correct type

#### Scenario: Opt-out state is per-user and per-type

- GIVEN "frank" turns on push for `dashboard_updated` and "grace" keeps the default (off)
- WHEN a group dashboard they both see is updated
- THEN a `dashboard_updated` row MUST be written for "frank" and for "grace"
- AND "frank"'s stored preference for `dashboard_updated` MUST read push on while "grace"'s reads push off

### Requirement: REQ-ACT-010 Subject/Message Templates and Translation

The system MUST define a complete set of translated subject and message templates for all 13 event types in both `en` and `nl`. NC Activity's `{placeholder}` substitution MUST be used for actor names, dashboard names, recipients, and roles. Templates MUST be registered as i18n keys.

#### Scenario: First-person subject rendered for self-actions

@e2e exclude never built: no code path in lib/ emits dashboard_created (grep `Extension::EVENT_CREATED` in lib/ finds only the catalogue in lib/Activity/Extension.php), so there is no action to drive and no row to read; no test exists. The en/nl templates are pinned by tests/Unit/Activity/ExtensionTest.php::testParseRendersTheRecordedSubjects

- GIVEN user "alice" creates a dashboard "Analytics"
- WHEN her activity row is rendered in the NC Activity UI
- THEN the subject MUST read `"You created dashboard Analytics"` (en) or `"Je hebt dashboard Analytics aangemaakt"` (nl)

#### Scenario: Third-person subject rendered for others' actions

@e2e exclude never built: no code path in lib/ emits dashboard_commented (grep `Extension::EVENT_COMMENTED` in lib/ finds only the catalogue in lib/Activity/Extension.php), so there is no action to drive and no row to read; no test exists

- GIVEN user "bob" comments on "alice"'s dashboard "Analytics" and "alice" receives the activity row
- WHEN "alice" views her activity stream
- THEN the subject MUST read `"bob commented on dashboard Analytics"` (en)

#### Scenario: `dashboard_commented` message includes comment excerpt

@e2e exclude never built: no code path in lib/ emits dashboard_commented (grep `Extension::EVENT_COMMENTED` in lib/ finds only the catalogue in lib/Activity/Extension.php), so there is no action to drive and no row to read; no test exists. The 200-character message cut is pinned by the "publish long message" case of tests/Unit/Activity/ActivityPublisherGoldenTest.php::testRowsMatchTheRecordedFixture

- GIVEN "bob" posts the comment "Great work on the Q2 charts!" on dashboard "Analytics"
- WHEN the activity row is rendered
- THEN the message MUST contain the first 200 characters of the comment body
- AND the subject MUST be the short template (not the full comment body)

#### Scenario: Missing translation key falls back to English

- GIVEN a user has an NC locale set to a language other than `en` or `nl` that LaunchPad has not translated
- WHEN their activity row is rendered
- THEN NC Activity MUST fall back to the English template
- NOTE: This is NC's standard fallback behaviour; LaunchPad only needs to provide `en` and `nl`

#### Scenario: Placeholder substitution does not leave unresolved tokens

- GIVEN a subject template `"{actor} updated dashboard {dashboard}"` is rendered with actor `"root"` and dashboard `"Marketing"`
- WHEN NC Activity applies substitution
- THEN the rendered string MUST be `"root updated dashboard Marketing"` with no literal `{actor}` or `{dashboard}` tokens remaining

### Requirement: REQ-ACT-011 Cross-Capability Emission Contract

Each of the 13 event types has exactly one owning capability responsible for calling `ActivityPublisher::publish()` at the correct point in the action lifecycle. This spec defines the types and publisher service; the sibling capabilities add the call-sites. A capability MUST NOT emit an event type it does not own.

#### Scenario: `dashboard_published` emitted only by `dashboard-draft-published` capability

@e2e exclude its AND clause is a source-code property (no other code path emits dashboard_published) that no running instance shows; exactly-once on a transition is pinned by tests/Unit/Activity/DashboardActivityEmissionTest.php::testPublishingAGroupDashboardTellsTheGroupOnce

- GIVEN a dashboard is transitioned to `published` status
- WHEN the `dashboard-draft-published` service method completes
- THEN `ActivityPublisher::publish('dashboard_published', ...)` MUST be called exactly once
- AND no other capability code path MUST call `publishActivity` for `dashboard_published`

#### Scenario: Emission occurs after domain action is persisted

@e2e exclude its AND clause needs a mapper that throws before publish(), which a test cannot cause on a running instance; no test pins either clause. The ordering is visible in lib/Service/DashboardService.php (mapper update(), then activity->updated())

- GIVEN the dashboard mapper has flushed the status update to the database
- WHEN `ActivityPublisher::publish()` is subsequently called
- THEN the activity row timestamp MUST be >= the database row's `updatedAt`
- AND if the mapper call fails and throws before `publish()`, no activity row MUST be written

#### Scenario: Activity failure does not fail the HTTP request

@e2e exclude needs IManager::publish() to throw on a running instance, which a test cannot cause; the swallow-and-log-ERROR path is pinned by the "publish failing manager" case of tests/Unit/Activity/ActivityPublisherGoldenTest.php::testRowsMatchTheRecordedFixture

- GIVEN `IManager::publishActivity()` throws `\Exception` (e.g. DB error on `oc_activity`)
- WHEN `ActivityPublisher` catches the exception
- THEN the HTTP response from the owning capability endpoint MUST still be HTTP 200/201/204
- AND an ERROR-level log entry MUST be written by `ActivityPublisher`

#### Scenario: No direct `oc_activity` writes in sibling-capability code

@e2e exclude a source-code property with no runtime outcome; no test pins it. Checked by grep: lib/ has no oc_activity SQL and no \OC_DB:: call

- GIVEN any PHP file in a sibling capability (e.g. `lib/Service/PublicationService.php`)
- WHEN the file is inspected
- THEN it MUST NOT contain any direct `oc_activity` SQL or `\OC_DB::` calls for LaunchPad events
- AND all event emission MUST go through `ActivityPublisher::publish()` or `ActivityPublisher::publishToRecipients()`

#### Scenario: Emission contract documented in Extension class docblock

@e2e exclude a docblock property with no runtime outcome; no test pins it. The table is in lib/Activity/Extension.php:10-28 (14 types, dashboard_acknowledged included)

- GIVEN `lib/Activity/Extension.php` is read
- WHEN the class-level docblock is inspected
- THEN it MUST list all 13 event types with the owning capability for each, so future contributors know where to add call-sites

### Requirement: REQ-ACT-011b Unit-Test Contract

@e2e exclude these scenarios describe the PHPUnit suite and the static-analysis run themselves, so there is nothing for an e2e to drive; they are pinned by tests/Unit/Activity/ExtensionTest.php (testPublishPopulatesCanonicalFields, testGetIconReturnsNonEmptyForAllTypes, testParseSetsRichSubjectForAllTypes, testReactionDebounceSuppressesSecondEmission) and by `composer check:strict` in CI

The system MUST include a PHPUnit test class `tests/Unit/Activity/ExtensionTest.php` that verifies each of the 13 event types is correctly published, that icons and subjects are resolved, and that the debounce logic suppresses duplicate rows.

#### Scenario: All 13 event types produce a row with correct fields

- GIVEN `ActivityPublisher` is instantiated with a mock `IManager`
- WHEN `publish($type, 'alice', 'uuid-1', 'Dashboard A', 'https://nc.example/...')` is called for each of the 13 event types
- THEN the mock `IManager::publishActivity()` MUST be called exactly once per event type
- AND the `IEvent` passed MUST have `app = 'launchpad'`, `type = $type`, `object_type = 'launchpad_dashboard'`, `object_id = 'uuid-1'`

#### Scenario: `Extension::getIcon()` returns non-empty URL for all 13 types

- GIVEN `Extension::getIcon($type)` is called for each value in `Extension::ALL_EVENTS`
- THEN each call MUST return a non-empty string
- AND no call MUST throw

#### Scenario: `Extension::parse()` sets `richSubject` for all 13 types

- GIVEN `Extension::parse()` is called with a mock `IEvent` for each of the 13 types, with both self-actor and other-actor variants
- THEN the returned `IEvent` MUST have `richSubject` set to a non-empty string for every combination (26 assertions total)

#### Scenario: Debounce suppresses second `dashboard_reacted` within window

- GIVEN a mock or in-memory APCu stub is used
- AND `publish('dashboard_reacted', 'bob', 'uuid-1', ...)` has already been called once
- WHEN `publish('dashboard_reacted', 'bob', 'uuid-1', ...)` is called a second time within 900 s
- THEN `IManager::publishActivity()` MUST have been called exactly once in total (the second call is suppressed)

#### Scenario: PHPStan level 8 and PHPCS PSR-12 pass on the test file

- GIVEN `tests/Unit/Activity/ExtensionTest.php` is committed
- WHEN `composer check:strict` is executed
- THEN both PHPStan and PHPCS MUST report zero errors or warnings for the new test file
- AND any pre-existing issues in nearby files encountered during the task MUST also be fixed

### Requirement: REQ-ACT-007 Reaction debounce holds regardless of deployment topology

@e2e exclude needs an instance without APCu (CI runs one topology), and dashboard_reacted is never emitted; pinned by tests/Unit/Activity/DebounceHelperApcuTest.php::testADisabledApcuFallsBackToTheDistributedCache, ::testAnEnabledApcuAnswersTheClaimAndTheCacheIsNotUsed and tests/Unit/Activity/DebounceHelperTest.php::testDistributedCachePathDebouncesAcrossClaims

The per-`(actorUserId, dashboardUuid)` reaction debounce (900-second window) MUST hold across HTTP requests and PHP-FPM worker processes on every supported deployment topology, including installations where the
APCu PHP extension is not installed or not enabled for the running SAPI.
The debounce MUST NOT silently degrade to "always allow" when APCu is
unavailable.

#### Scenario: Debounce holds without APCu across two separate requests

- **GIVEN** a Nextcloud instance with APCu not installed
- AND user "bob" reacted to dashboard `abc` in request 1, one second ago
- **WHEN** bob reacts to dashboard `abc` again in a separate HTTP request
  (request 2) within the same 900-second window
- **THEN** `allowReaction('bob', 'abc')` MUST return `false` in request 2
- **AND** no second reaction activity row MUST be written

#### Scenario: Debounce still uses APCu when available

- **GIVEN** a Nextcloud instance with APCu installed and enabled
- **WHEN** `allowReaction()` is called
- **THEN** the claim MUST be served via `apcu_add()` exactly as before this
  change (no regression to the fast path)

### Requirement: REQ-ACT-008 Global fan-out debounce holds regardless of deployment topology

@e2e exclude needs an instance without APCu, or a fake clock injected into DebounceHelper; pinned by tests/Unit/Activity/DebounceHelperTest.php::testDistributedCachePathDebouncesGlobalFanout and tests/Unit/Activity/DebounceHelperClockTest.php::testAFakeClockNeverUsesTheDistributedCache

The per-`(dashboardUuid, eventType)` fan-out debounce (900-second window) MUST hold across HTTP requests and PHP-FPM worker processes, including on installations without APCu.

#### Scenario: Fan-out debounce holds without APCu across two separate requests

- **GIVEN** a Nextcloud instance with APCu not installed
- AND a `dashboard_updated` fan-out for dashboard `abc` was performed in
  request 1
- **WHEN** a second `dashboard_updated` event for dashboard `abc` arrives in
  a separate HTTP request within the same 900-second window
- **THEN** `allowGlobalFanout('abc', 'dashboard_updated')` MUST return
  `false` in request 2
- **AND** the fan-out MUST be skipped silently, exactly as the existing
  single-request scenario requires

#### Scenario: Deterministic unit tests remain unaffected

- **GIVEN** a test injects a fake clock into `DebounceHelper`
- **WHEN** the test advances the fake clock past the 900-second window
- **THEN** the helper MUST continue to use the in-memory fallback for
  deterministic behaviour, unaffected by this change

### Requirement: LaunchPad events can reach the activity digest email (REQ-DIGE-001)

@e2e exclude both scenarios end in the content of a digest email, which needs NC Activity's mail cron job, a week of time and a mail sink; the CI fixture has no mail catcher. The mail defaults (on for shared/published, off for updated/acknowledged) are pinned by tests/Unit/Activity/Settings/ActivitySettingsTest.php::testDefaultsAndGroup

LaunchPad MUST register activity settings for `dashboard_shared`, `dashboard_published`, `dashboard_updated` and `dashboard_acknowledged` in one group named "LaunchPad", each changeable for the stream and for mail. Mail MUST default to on for `dashboard_shared` and `dashboard_published` and to off for the other two.

#### Scenario: Pieter gets a weekly email

- **GIVEN** Pieter set his Nextcloud activity email to weekly and left the LaunchPad defaults
- **WHEN** Karin shares the dashboard "Team Vergunningen" with him on Tuesday
- **THEN** his weekly activity email lists "Karin shared the dashboard Team Vergunningen with you"

#### Scenario: Updates stay out of the email by default

- **GIVEN** Pieter left the LaunchPad defaults
- **WHEN** a dashboard shared with him is edited
- **THEN** the change shows in his activity stream and not in his digest email

### Requirement: Sharing and publishing emit activity events (REQ-DIGE-002)

LaunchPad MUST emit `dashboard_shared` to the recipients when a dashboard is shared, and `dashboard_published` to the target audience when a dashboard becomes published, including the first time a scheduled dashboard is surfaced as published. The person who acted MUST NOT receive a recipient row of their own. For a share, the sharer MUST instead receive the one row that names every recipient (REQ-ACT-005, Ruben 21 Nov 2026).

#### Scenario: Group dashboard published

- **GIVEN** Noor schedules the group dashboard "Organisatie" for group "Medewerkers" to publish on Monday 08:00
- **WHEN** it is first surfaced as published after that time
- **THEN** each member of "Medewerkers" gets one `dashboard_published` activity for "Organisatie"
- **AND** Noor gets none

### Requirement: Updates to shared dashboards are summarised, not repeated (REQ-DIGE-003)

LaunchPad MUST emit `dashboard_updated` to the audience of a shared or group dashboard when it is saved, at most once per dashboard per 24 hours. A save of a private dashboard MUST NOT write any activity row (REQ-ACT-004).

#### Scenario: Ten edits in one morning

- **GIVEN** Karin edits the shared dashboard "Team Vergunningen" ten times on one morning
- **WHEN** Pieter looks at his activity stream that afternoon
- **THEN** he sees one entry "Karin updated the dashboard Team Vergunningen"
