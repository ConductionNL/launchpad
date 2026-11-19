---
status: done
---

# Dashboard Cascade Events Specification

## Purpose

When a LaunchPad dashboard is deleted, all dependent data (widget placements, comments, reactions, locks, versions, public shares, metadata values, translations, view analytics, child-tree dashboards) MUST be automatically removed. When a Nextcloud user or group is deleted, their associated dashboards and downstream records MUST likewise be cleaned up. This capability defines the event, the listener registry, failure isolation, idempotency, and cascade stats reporting.

## Requirements


### Requirement: REQ-CSC-001 DashboardDeletedEvent Definition

The system MUST define a `DashboardDeletedEvent` class at `lib/Event/DashboardDeletedEvent.php` that carries all context listeners need to perform targeted cleanup.

#### Scenario: Event carries required payload

- GIVEN `DashboardService::delete()` soft-deletes a dashboard with UUID `abc-123`, owner `alice`, type `user`, at `2026-05-01T10:00:00Z`
- WHEN the event is constructed
- THEN `getDashboardUuid()` MUST return `'abc-123'`
- AND `getOwnerUserId()` MUST return `'alice'`
- AND `getType()` MUST return `'user'`
- AND `getDeletedAt()` MUST return a `\DateTimeImmutable` equal to `2026-05-01T10:00:00Z`
- AND `getDashboardId()` MUST return the deleted row's integer id when the dispatcher supplied it, and `null` otherwise
- NOTE: every dispatcher fires after the dashboard row is gone, so a listener for a table keyed on `dashboard_id` cannot recover the id from the UUID. It MUST read `getDashboardId()`. Every dispatcher in LaunchPad supplies it.

@e2e exclude event payload shape with no HTTP surface; asserted in tests/Unit/Event/DashboardDeletedEventTest.php

#### Scenario: Event is dispatched after soft-delete and before response

- GIVEN a dashboard `D1` is being deleted
- WHEN `DashboardService::delete()` runs
- THEN the dashboard row MUST be soft-deleted in `oc_launchpad_dashboards` first
- AND `DashboardDeletedEvent` MUST be dispatched second, before the HTTP response is returned
- NOTE: dispatch is synchronous within the same PHP request — `IEventDispatcher::dispatchTyped()` blocks until all listeners return; the event fires before the HTTP response is sent. This matches the pattern confirmed in the reference implementation.
- NOTE: listeners observe a row that is already soft-deleted — they MUST NOT attempt to re-delete the main row

@e2e exclude the order inside one request (row removed first, event dispatched second, both before the response) is not observable over HTTP, which only sees the finished state. Pinned by tests/Unit/Service/DashboardCascadeEventDispatchTest.php::testDeleteDashboardDispatchesEvent; that the listeners' effects are already in place when the response arrives is exercised by every test in tests/e2e/dashboard-cascade-events.spec.ts, which reads back immediately after the DELETE.

#### Scenario: Event is NOT dispatched when validation rejects the delete

- GIVEN dashboard `D1` has child dashboards and the caller has NOT requested cascade mode
- WHEN `DashboardService::delete()` runs validation
- THEN the validation MUST reject the request (HTTP 400 or equivalent)
- AND `DashboardDeletedEvent` MUST NOT be dispatched
- AND no dependent data MUST be touched

#### Scenario: Event carries correct type for group-shared dashboard

- GIVEN a group-shared dashboard `G1` (type `group_shared`) is deleted by an admin
- WHEN `DashboardDeletedEvent` is dispatched
- THEN `getType()` MUST return `'group_shared'`
- AND `getOwnerUserId()` MUST return the admin's user ID (the actor who performed the deletion)

@e2e exclude event payload with no HTTP surface. Pinned by tests/Unit/Event/DashboardDeletedEventTest.php::testGroupSharedTypeAndActorOwner and tests/Unit/Service/DashboardCascadeEventDispatchTest.php::testDeleteGroupSharedDispatchesEvent.

#### Scenario: Event class extends Nextcloud IEventDispatcher contract

- GIVEN `DashboardDeletedEvent` is instantiated
- WHEN it is passed to `IEventDispatcher::dispatchTyped()`
- THEN no type error MUST occur — the class MUST extend `\OCP\EventDispatcher\Event`

@e2e exclude a class-hierarchy property with no HTTP surface. Pinned by tests/Unit/Event/DashboardDeletedEventTest.php::testExtendsNextcloudEventBase.

### Requirement: REQ-CSC-002 Listener Registry and Registration

Every listener MUST be registered in `Application` via `IEventDispatcher::addListener`. Adding a new listener MUST require only adding one registration line — no edits to existing listener classes.

#### Scenario: All DashboardDeletedEvent listeners are registered

- GIVEN LaunchPad is bootstrapped
- WHEN `Application::register()` runs
- THEN `IEventDispatcher` MUST have listeners registered for `DashboardDeletedEvent::class` covering: `WidgetPlacementsListener`, `CommentsListener`, `ReactionsListener`, `LocksListener`, `VersionsListener`, `PublicSharesListener`, `DashboardSharesListener`, `MetadataValuesListener`, `TranslationsListener`, `ViewAnalyticsListener`, `TreeListener`

@e2e exclude registration is wiring with no HTTP surface; asserted by running Application::register() in tests/Unit/Listener/DashboardSharesListenerTest.php

#### Scenario: Lifecycle listeners are registered for NC events

- GIVEN LaunchPad is bootstrapped
- WHEN a `\OCP\User\Events\UserDeletedEvent` is dispatched by Nextcloud core
- THEN LaunchPad's `UserDeletedListener` MUST be invoked
- AND when a `\OCP\Group\Events\GroupDeletedEvent` is dispatched
- THEN LaunchPad's `GroupDeletedListener` MUST be invoked

#### Scenario: New listener can be added without editing existing code

- GIVEN a developer creates `lib/Listener/FavoritesListener.php` implementing `IEventListener`
- WHEN they add one line to `Application::register()` registering it for `DashboardDeletedEvent::class`
- THEN it MUST be invoked on every subsequent dashboard deletion — no changes to other listener files, `DashboardService`, or the event class are required

@e2e exclude a source-structure property (which files a developer edits), not runtime behaviour an instance can show. The one-line registration per listener is visible in lib/AppInfo/Application.php and pinned for one listener by tests/Unit/Listener/DashboardSharesListenerTest.php::testIsRegisteredForDashboardDeletedEvent.

### Requirement: REQ-CSC-003 Dependent-Data Listener Group

A set of listeners MUST clean up every dependent table when `DashboardDeletedEvent` fires. Listeners for disjoint data MUST execute independently and MUST NOT interfere with each other.

- NOTE: Of the eleven listeners in this group, only `CommentsListener` is derived from the reference implementation's pattern, which uses `ICommentsManager::deleteCommentsAtObject()` as its sole cleanup mechanism for a single listener registered on the delete event.
- NOTE: The remaining ten listeners: `WidgetPlacementsListener`, `ReactionsListener`, `LocksListener`, `VersionsListener`, `PublicSharesListener`, `DashboardSharesListener`, `MetadataValuesListener`, `TranslationsListener`, `ViewAnalyticsListener`, and `TreeListener` — are LaunchPad-specific additions. The reference implementation cleans those targets (if they exist at all) either inline in the service layer or not at all; LaunchPad's richer schema warrants dedicated listeners. The table list MUST be validated against LaunchPad's actual migration files before implementation.
- NOTE: `TreeListener` (see REQ-CSC-010) is a LaunchPad improvement over the reference implementation. The reference silently deletes child pages via a recursive filesystem operation without firing the delete event for each child, meaning child-level dependent data (locks, analytics, etc.) is never cleaned up. LaunchPad's `TreeListener` corrects this gap by explicitly dispatching `DashboardDeletedEvent` for each child so that the full listener stack runs for every node in the tree.

#### Scenario: Widget placements are deleted on dashboard delete

- GIVEN dashboard `D1` has 5 widget placements in `oc_launchpad_widget_placements`
- WHEN `DashboardDeletedEvent` fires for `D1`
- THEN `WidgetPlacementsListener` MUST delete all 5 rows
- AND no placements from other dashboards MUST be touched
- NOTE: LaunchPad-specific listener — the reference implementation has no equivalent widget placement table.

#### Scenario: Comments are removed via ICommentsManager

- GIVEN dashboard `D1` has comments tracked by NC core
- WHEN `CommentsListener` handles the event
- THEN it MUST call `ICommentsManager::deleteCommentsAtObject('launchpad_dashboard', $D1.uuid)`
- AND the NC comments table MUST contain no comments for `launchpad_dashboard` / `D1.uuid` after the call
- NOTE: This is the one listener whose pattern is directly derived from the reference implementation. The reference registers a single listener that calls the equivalent ICommentsManager method as its only cleanup action.

@e2e exclude retired: dashboard comments, CommentsListener and its registration were removed in c4a515e7c ("chore(retire): remove Comments, outbound RSS feed, and save-as-template"), an ancestor of HEAD. No comment can be created on a dashboard, so there is nothing to cascade.

#### Scenario: Public shares are soft-revoked, not hard-deleted

- GIVEN dashboard `D1` has 2 active rows in `oc_launchpad_public_shares` with `revokedAt IS NULL`
- WHEN `PublicSharesListener` handles the event
- THEN both rows MUST have `revokedAt` set to the current timestamp
- AND neither row MUST be hard-deleted (audit trail is preserved)
- NOTE: LaunchPad-specific listener — the reference implementation has no public-shares table.

@e2e exclude the distinguishing clause (row kept with `revokedAt` set, not deleted) is not observable over HTTP: a revoked and a deleted token both answer 404 at `/s/{token}/data`, and the share list endpoint 404s once the dashboard is gone. Pinned by tests/Unit/Db/PublicShareMapperCoverageTest.php::testRevokeByDashboardUuidOnlyTouchesUnrevokedSharesAndReturnsTheCount and the PublicSharesListener cases of tests/Unit/Listener/CleanupListenersGoldenTest.php::testListenerMatchesTheRecordedFixture.

#### Scenario: User and group shares are deleted with their dashboard

- GIVEN dashboard `D1` is shared with user `bob` and with group `sales`, two rows in `oc_launchpad_dashboard_shares`
- WHEN `D1` is deleted and `DashboardDeletedEvent` fires
- THEN `DashboardSharesListener` MUST delete both rows, keyed on the event's `getDashboardId()`
- AND neither `bob` nor a member of `sales` MUST see `D1` any longer
- AND no share row pointing at `D1` MUST remain for the orphan sweep to find
- NOTE: until this listener existed, the rows stayed behind until `OrphanedSharesCategory` swept them. That sweep stays, for rows orphaned before the fix.

#### Scenario: Versions file is deleted in GroupFolder mode

- GIVEN the instance uses GroupFolder storage backend
- AND dashboard `D1` has a JSON versions file at `<groupfolder>/LaunchPad/versions/D1.uuid.json`
- WHEN `VersionsListener` handles the event
- THEN the DB rows in `oc_launchpad_dash_versions` MUST be deleted
- AND the JSON file MUST be removed from GroupFolder storage
- NOTE: if the file does not exist (already removed or never created), the listener MUST treat this as a no-op
- NOTE: LaunchPad-specific listener — the reference implementation has no versioning table.

@e2e exclude needs an instance running the GroupFolder storage backend, which the e2e instance does not provision, and a file read outside the API. Pinned (DB rows) by tests/Unit/Listener/VersionsListenerTest.php::testDeletedDashboardHasItsVersionRowsRemoved; no test covers the GroupFolder file removal.

#### Scenario: Tree listener recursively cascades to child dashboards

- GIVEN dashboard `P1` (parent) has children `C1` and `C2` in `oc_launchpad_dashboards`
- WHEN `DashboardDeletedEvent` fires for `P1` (cascade-delete mode)
- THEN `TreeListener` MUST dispatch a new `DashboardDeletedEvent` for `C1` and another for `C2`
- AND each child event MUST trigger the full listener stack for that child (placements, comments, etc.)
- NOTE: LaunchPad improvement — the reference implementation relies on a recursive filesystem delete that wipes child nodes silently without firing the delete event for each child. This leaves child-level DB rows (locks, analytics, etc.) orphaned. `TreeListener` fixes this gap explicitly.

### Requirement: REQ-CSC-004 User Lifecycle Cleanup

When Nextcloud deletes a user, LaunchPad MUST clean up all data owned by that user.

- NOTE: LaunchPad-specific design. The reference implementation's `UserDeletedListener` does NOT enumerate or delete owned pages on user deletion — it only removes user-scoped DB rows (analytics, locks) and bulk-wipes IConfig preferences. LaunchPad dashboards are row-owned by user (via `ownerUserId`), so cascade deletion of owned dashboards requires explicit enumeration logic — this is not a port of the reference's behaviour.

#### Scenario: Personal dashboards are deleted on user deletion

- GIVEN user `alice` owns 3 personal dashboards in `oc_launchpad_dashboards`
- WHEN Nextcloud dispatches `\OCP\User\Events\UserDeletedEvent` for `alice`
- THEN `UserDeletedListener` MUST call `DashboardService::delete()` for each of alice's 3 dashboards
- AND each deletion MUST dispatch `DashboardDeletedEvent`, cascading cleanup of their dependent data
- AND no other user's dashboards MUST be affected
- NOTE: LaunchPad-specific design — the reference implementation does not delete owned content on user deletion. LaunchPad dashboards are DB-row-owned and must be explicitly enumerated and deleted here.

#### Scenario: Role assignments are removed on user deletion

- GIVEN `alice` has 2 rows in `oc_launchpad_role_assignments`
- WHEN `UserDeletedListener` handles the event
- THEN both rows MUST be deleted from `oc_launchpad_role_assignments`
- AND role assignments for other users MUST remain untouched

#### Scenario: Feed token is soft-revoked on user deletion

- GIVEN `alice` has an active RSS feed token in `oc_launchpad_feed_tokens`
- WHEN `UserDeletedListener` handles the event
- THEN the token row MUST have `revokedAt` set to the current timestamp
- AND it MUST NOT be hard-deleted (the token URL may still be live; revocation allows a 410 response)

@e2e exclude retired: the outbound RSS feed and FeedToken(+Mapper) were removed in c4a515e7c, which also adds migration Version002003Date20260620000000 dropping `launchpad_feed_tokens`. There is no feed token to revoke.

#### Scenario: Analytics opt-out preference is removed on user deletion

- GIVEN `alice` has a stored analytics opt-out preference via IConfig
- WHEN `UserDeletedListener` handles the event
- THEN the preference MUST be deleted from IConfig for her user ID
- NOTE: absence of the key is equivalent to "opted in" — this ensures the pref does not linger for a future user who receives the same user ID

### Requirement: REQ-CSC-005 Group Lifecycle Cleanup

When Nextcloud deletes a group, LaunchPad MUST clean up all group-scoped data.

- NOTE: LaunchPad-specific requirement with no reference precedent. The reference implementation has no `GroupDeletedListener` and does not register any handler for `GroupDeletedEvent`. The `GroupDeletedListener` must be designed from scratch. The IConfig JSON-mutation scenarios (removing group from `org_navigation_tree` and `group_order`) are LaunchPad-specific features requiring LaunchPad schema confirmation before implementation.

#### Scenario: Group-shared dashboards are deleted on group deletion

- GIVEN group `marketing` owns 2 group-shared dashboards in `oc_launchpad_dashboards`
- WHEN Nextcloud dispatches `\OCP\Group\Events\GroupDeletedEvent` for `marketing`
- THEN `GroupDeletedListener` MUST call `DashboardService::delete()` for each of those 2 dashboards
- AND each deletion MUST cascade cleanup of dependent data via `DashboardDeletedEvent`
- NOTE: LaunchPad-specific design — no equivalent exists in the reference implementation.

@e2e exclude never built: GroupDeletedListener only calls RoleService::deleteByGroupId (lib/Listener/GroupDeletedListener.php). Deleting group-shared dashboards was a TODO in the original stub (fa102a8a0) and `git log --all -S'deleteGroupShared' -- lib/Listener` shows no commit that implemented it. No unit or Newman test covers it.

#### Scenario: Group is removed from org navigation tree on group deletion

- GIVEN the `launchpad.org_navigation_tree` IConfig value is a JSON object containing `groupVisibility` arrays that reference group `marketing`
- WHEN `GroupDeletedListener` handles the event
- THEN it MUST read the JSON, remove `'marketing'` from all `groupVisibility` arrays, and write the updated JSON back to IConfig
- AND no other group identifiers in the JSON MUST be altered
- NOTE: LaunchPad-specific IConfig JSON-mutation — no equivalent in the reference implementation.

@e2e exclude never built: GroupDeletedListener only removes role assignments; the groupVisibility rewrite was a TODO in the stub (fa102a8a0) and `git log --all -S'groupVisibility' -- lib/Listener` finds only that stub and its archive. No unit or Newman test covers it.

#### Scenario: Group is removed from group_order setting on group deletion

- GIVEN `launchpad.group_order` is a JSON array `["engineering", "marketing", "support"]`
- WHEN `GroupDeletedListener` handles the event for `marketing`
- THEN the setting MUST be updated to `["engineering", "support"]`
- AND the update MUST be persisted to IConfig
- NOTE: LaunchPad-specific IConfig JSON-mutation — no equivalent in the reference implementation.

@e2e exclude never built: GroupDeletedListener only removes role assignments; the group_order rewrite was a TODO in the stub (fa102a8a0) and `git log --all -S'group_order' -- lib/Listener` finds only that stub and its archive. No unit or Newman test covers it.

### Requirement: REQ-CSC-006 Failure Isolation

A failure in one listener MUST NOT prevent other listeners from executing. Every listener MUST catch all `\Throwable` and continue.

- NOTE: The reference implementation uses `error`-level logging on listener failure. LaunchPad deliberately downgrades this to `warning` level on the basis that cascade failures are recoverable via the orphan-cleanup job and do not represent fatal errors. This is an intentional divergence.

#### Scenario: One listener throws — others still execute

- GIVEN `DashboardDeletedEvent` fires for `D1`
- AND `ReactionsListener` throws a `\RuntimeException` during its execution
- WHEN the event is dispatched
- THEN `WidgetPlacementsListener`, `CommentsListener`, `LocksListener`, and all other registered listeners MUST still execute
- AND the exception from `ReactionsListener` MUST NOT propagate beyond its own catch block
- AND `DashboardService::delete()` MUST complete and return a response

@e2e exclude needs a listener to throw inside a live request, a fault an e2e run cannot inject. Pinned by tests/Unit/Listener/DashboardSharesListenerTest.php::testAFailingDeleteIsLoggedAndDoesNotStopPeerListeners and tests/Unit/Listener/ReactionsListenerTest.php::testServiceFailureIsLoggedAndSwallowed.

#### Scenario: Listener failure is logged at WARN level

- GIVEN a listener throws during handling of `DashboardDeletedEvent`
- WHEN the catch block executes
- THEN it MUST call `ILogger::warning(...)` (or equivalent) with the listener class name, dashboard UUID, and exception message
- AND MUST NOT call `ILogger::error()` (a cascade failure is recoverable via orphan-cleanup — it is not a fatal error)
- NOTE: The reference implementation logs at `error` level; LaunchPad's choice of `warning` is deliberate — it signals recoverability rather than a fatal condition.

@e2e exclude needs an injected listener fault and a read of the server log, neither reachable over HTTP. Pinned by tests/Unit/Listener/DashboardCleanupListenerTest.php::testAnErrorIsCaughtAndLoggedAsAWarning.

#### Scenario: Multiple listener failures do not prevent response

- GIVEN 3 out of 10 listeners throw exceptions
- WHEN the dispatch completes
- THEN `DashboardService::delete()` MUST still return the HTTP response with `cascadeStats`
- AND `cascadeStats` MUST accurately reflect counts from the 7 listeners that succeeded
- AND the 3 failures MUST each be logged at WARN level with full context (listener class, dashboard UUID, exception message)
- NOTE: Failure recording via a dedicated DB table (`oc_launchpad_cascade_failures`) has been dropped in favour of log-and-continue. The orphan-cleanup job identifies residual rows by querying dependent tables directly, making the failures table redundant. See REQ-CSC-007 for the rationale.

@e2e exclude needs three injected listener faults, and its `cascadeStats` clause was never built (see cascadestats-is-present-even-when-no-dependent-data-exists). The log-and-continue part is pinned by tests/Unit/Listener/DashboardCleanupListenerTest.php::testAnErrorIsCaughtAndLoggedAsAWarning and the `failing` cases of tests/Unit/Listener/CleanupListenersGoldenTest.php::testListenerMatchesTheRecordedFixture.

### Requirement: REQ-CSC-007 Failure Handling — Log-and-Continue

When a listener fails, the failure MUST be logged at WARN level with full context. No separate failure-recording table is required.

- NOTE: The original spec included an `oc_launchpad_cascade_failures` table for per-listener failure recording. The reference implementation has no such table and relies purely on log-and-continue: a try/catch in each listener logs on failure and the delete proceeds regardless. Given that the orphan-cleanup job can identify residual rows by querying dependent tables directly (e.g., widget placements whose dashboard is soft-deleted), the failures table adds migration cost without meaningful benefit. It has been removed from this spec. If the orphan-cleanup job's retry strategy requires a failure index in the future, that concern belongs in the `orphaned-data-cleanup` change.

#### Scenario: Listener failure is fully logged before continuing

- GIVEN `MetadataValuesListener` throws while handling a `DashboardDeletedEvent` for UUID `abc-123`
- WHEN the catch block runs
- THEN `ILogger::warning()` MUST be called with at minimum: the listener class name, the dashboard UUID `abc-123`, and the exception message
- AND execution MUST continue — no exception MUST propagate out of the listener's catch block

@e2e exclude needs an injected MetadataValuesListener fault and a read of the server log. Pinned by the MetadataValuesListener `failing` case of tests/Unit/Listener/CleanupListenersGoldenTest.php::testListenerMatchesTheRecordedFixture (warning with class, uuid `d-1` and message).

#### Scenario: No failure-recording table migration is required

- GIVEN the implementation of this change
- WHEN the migration list is reviewed
- THEN there MUST be no migration adding an `oc_launchpad_cascade_failures` table
- AND the orphan-cleanup job MUST identify stragglers by querying dependent tables directly (e.g., `oc_launchpad_widget_placements` joined against soft-deleted dashboards)

@e2e exclude a property of the migration list, not runtime behaviour: no file under lib/Migration mentions `cascade_failures` (grep). The straggler query side is the orphaned_widget_placements category, covered in openspec/specs/orphaned-data-cleanup.

### Requirement: REQ-CSC-008 Idempotency

Every listener MUST be idempotent: running it a second time against already-cleaned data MUST be a no-op with no errors.

#### Scenario: Re-running WidgetPlacementsListener on empty table is safe

- GIVEN `DashboardDeletedEvent` fires for `D1` and placements are already deleted
- WHEN `WidgetPlacementsListener` runs again (e.g., via orphan-cleanup retry)
- THEN the DELETE query MUST affect 0 rows and MUST NOT throw an exception
- AND the listener MUST return successfully

@e2e exclude a second run of a listener for an already-deleted dashboard cannot be caused over HTTP: a repeated DELETE answers 404 before any event fires. Pinned by tests/Unit/Listener/WidgetPlacementsListenerTest.php::testHandlesDashboardDeletedEventWithoutThrowing.

#### Scenario: Re-running PublicSharesListener on already-revoked shares is safe

- GIVEN all share rows for `D1` already have `revokedAt` set
- WHEN `PublicSharesListener` runs again
- THEN the UPDATE MUST match 0 rows (WHERE `revokedAt IS NULL` filters them all out) and MUST NOT throw

@e2e exclude a second listener run cannot be caused over HTTP (a repeated DELETE answers 404 first). Pinned by tests/Unit/Db/PublicShareMapperCoverageTest.php::testRevokeByDashboardUuidOnlyTouchesUnrevokedSharesAndReturnsTheCount (the UPDATE filters `revoked_at IS NULL`).

#### Scenario: Re-running CommentsListener when no comments exist is safe

- GIVEN `ICommentsManager::deleteCommentsAtObject()` is called for `D1` when no comments exist
- THEN it MUST return without error (NC core already handles the empty-case gracefully)
- AND `CommentsListener` MUST NOT add extra null-checks that could mask genuine errors

@e2e exclude retired: CommentsListener was removed in c4a515e7c ("chore(retire): remove Comments, outbound RSS feed, and save-as-template").

### Requirement: REQ-CSC-009 Cascade Stats Response

`DashboardService::delete()` MUST return cascade statistics alongside the standard delete response.

- NOTE: The reference implementation returns only `['success' => true]` with no cascade stats. The `cascadeStats` response shape is an additive LaunchPad-specific capability. Tree-child deletions MUST contribute to the aggregate counts (e.g., total `widgetPlacementsDeleted` across parent + all children), which requires `TreeListener` to propagate child listener counts back up to `DashboardService` — a coordination mechanism the reference implementation does not need.

#### Scenario: Response includes cascadeStats on successful delete

- GIVEN dashboard `D1` has 3 widget placements, 2 reactions, 1 lock
- WHEN `DELETE /api/dashboards/D1.uuid` completes
- THEN the response body MUST include:
  ```json
  {
    "deletedAt": "2026-05-01T10:00:00Z",
    "cascadeStats": {
      "widgetPlacementsDeleted": 3,
      "commentsDeleted": 0,
      "reactionsDeleted": 2,
      "locksDeleted": 1,
      "versionsDeleted": 0,
      "sharesRevoked": 0,
      "metadataValuesDeleted": 0,
      "translationsDeleted": 0,
      "viewsDeleted": 0
    }
  }
  ```
- AND the response MUST be additive (clients that ignore unknown fields remain unaffected)

@e2e exclude never built: `DELETE /api/dashboard/{id}` answers `{"status":"ok"}` (DashboardApiController::delete) and `git log --all -S'cascadeStats' -- lib src appinfo` finds no commit. No unit or Newman test covers it.

#### Scenario: cascadeStats reflects partial success when listeners fail

- GIVEN 2 listeners succeed (deleting 5 rows total) and 1 listener fails
- WHEN the response is built
- THEN `cascadeStats` MUST report counts from the 2 successful listeners
- AND the failed listener's key MUST appear with value `0` (or be omitted — implementation choice, but MUST be consistent)
- NOTE: the caller is not expected to act on partial-failure counts; WARN-level log entries are the canonical failure record (no `oc_launchpad_cascade_failures` table exists)

@e2e exclude never built: there is no `cascadeStats` in any response (`git log --all -S'cascadeStats' -- lib src appinfo` is empty), and the listener fault cannot be injected over HTTP either.

#### Scenario: cascadeStats is present even when no dependent data exists

- GIVEN dashboard `D1` has no placements, comments, or any other dependent rows
- WHEN `DELETE /api/dashboards/D1.uuid` completes
- THEN `cascadeStats` MUST still be present in the response with all counters set to `0`

@e2e exclude never built: `DELETE /api/dashboard/{id}` answers `{"status":"ok"}` (DashboardApiController::delete); `git log --all -S'cascadeStats' -- lib src appinfo` finds no commit.

### Requirement: REQ-CSC-010 Tree Cascade Validation Guard

Cascade deletion of a parent dashboard MUST only proceed when explicitly requested; non-cascade deletes with children MUST be rejected before any event is dispatched.

- NOTE: LaunchPad improvement over the reference implementation. The reference deletes child nodes silently via a recursive filesystem operation (no cascade flag, no per-child event). LaunchPad's explicit cascade flag and `TreeListener` are more correct: each child receives its own `DashboardDeletedEvent` so all dependent listeners run for every node. The cascade guard additionally protects against accidental subtree deletion.

#### Scenario: Non-cascade delete with children is rejected before event dispatch

- GIVEN dashboard `P1` has child `C1`
- WHEN a caller sends `DELETE /api/dashboards/P1.uuid` without a cascade flag
- THEN the system MUST return HTTP 400 with an error indicating children exist
- AND `DashboardDeletedEvent` MUST NOT be dispatched for `P1` or `C1`
- AND no dependent data MUST be altered

#### Scenario: Cascade delete processes parent then recursively processes children

- GIVEN dashboard `P1` has children `C1` and `C2`, each with 2 widget placements
- WHEN `DELETE /api/dashboards/P1.uuid?cascade=true` is called
- THEN `DashboardService::delete()` MUST soft-delete `P1` and dispatch `DashboardDeletedEvent` for it
- AND `TreeListener` MUST dispatch `DashboardDeletedEvent` for `C1` and `C2`
- AND all 4 widget placements (2 per child) MUST be removed by `WidgetPlacementsListener` responding to each child event
- AND `cascadeStats` in the response MUST reflect the total across all three dashboards

@e2e exclude its last clause, `cascadeStats` totals in the response, was never built (`git log --all -S'cascadeStats' -- lib src appinfo` is empty). Everything else is asserted by the tree-listener-recursively-cascades-to-child-dashboards test in tests/e2e/dashboard-cascade-events.spec.ts; the per-node dispatch by tests/Unit/Service/DashboardCascadeEventDispatchTest.php::testDeleteSubtreeDispatchesEventForEachNode.

#### Scenario: Tree listener is a no-op for leaf dashboards

- GIVEN dashboard `L1` has no children
- WHEN `DashboardDeletedEvent` fires for `L1`
- THEN `TreeListener` MUST query the children table, find zero rows, and return without dispatching any further events
- AND this MUST be treated as a successful no-op (no failure is recorded)

@e2e exclude whether a listener dispatched nothing is not observable over HTTP; a leaf delete looks the same either way. Pinned by tests/Unit/Listener/TreeListenerTest.php::testNoChildrenDispatchesNothing.
