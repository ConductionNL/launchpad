# Dashboard mandatory-read acknowledgements and read receipts

## Why

LaunchPad already lets an admin **pin** a widget so a user cannot remove it
(`isCompulsory`, see the `widgets` and `admin-templates` capabilities). That
guarantees the widget is *present* on the user's start page — it does **not**
guarantee the user has *read* it, and it produces **no evidence** that anyone
did.

For the intranet / employee-portal use case that LaunchPad competes in, "the
user must confirm they read this and I can prove who did" is a table-stakes
capability, not a nice-to-have. The competitor scan (intelligence DB,
digital-workplace segment) shows it recurring across every major vendor as a
first-class feature under different names:

| Capability (competitor wording) | Vendors |
| --- | --- |
| Forced Delivery (critical comms) | Staffbase |
| Mandatory reads and acknowledgements | Simpplr |
| Read receipts and sign-off | Unily |
| Mandatory read / acknowledgement | Interact, Powell |

It is also a **compliance** feature (the intelligence DB files it under the
`compliance` category alongside ISO 27001 / GDPR posture): a gemeente rolling
out a new integriteitscode, a privacy policy update, or a safety notice needs
to record — per employee, with a timestamp — who has attested that they read
it, and to chase the ones who have not before a deadline.

LaunchPad has one adjacent mechanism today, and it is deliberately *not* this:
the `launchpad-compliance-audit-panel` widget stores a per-user
`content.acknowledgements: {deadlineId: dismissedAt}` map so a single user can
**dismiss their own** deadline alert. That is a private, per-user "snooze" with
no forced-delivery gate, no stable announcement identity across a template's
recipients, and — critically — **no admin-facing read-receipt report**. It
cannot answer "who in the Sociaal Domein team has *not* acknowledged the new
integriteitscode?". This change adds exactly that missing capability and
reuses the compliance-panel's acknowledgement vocabulary where it fits.

The capability extends the existing **compulsory-widget** concept rather than
inventing a new widget engine — it stays inside the ADR-049 widgets-as-config
model and the local-first, owns-its-tables architecture mandated by
`launchpad-adopt-or-abstractions` (no new install-time OpenRegister
dependency).

## What Changes

### Requiring acknowledgement (admin / template author)

- Add an **acknowledgement requirement** to a widget placement, expressed as
  new placement fields alongside the existing `isCompulsory`:
  `requiresAcknowledgement` (0/1), `acknowledgementPrompt` (the sign-off text,
  e.g. "I have read and understood the 2026 integriteitscode"),
  `acknowledgementDeadline` (nullable date), `reacknowledgeOnChange` (0/1), and
  `acknowledgementContentVersion` (integer, bumped by the author when the
  content materially changes).
- A stable `announcementKey` (UUID) is minted the first time acknowledgement is
  required on a **template** placement and is copied to every user's cloned
  placement, so all recipients of one announcement share one identity and the
  admin report can aggregate across them.
- Only users who may author the template (admin / template owner per the
  `permissions` and `role-feature-permissions` model) can set, change, or clear
  an acknowledgement requirement.

### Forced delivery + acknowledging (recipient)

- An unacknowledged mandatory item is surfaced with **forced delivery**: it is
  presented prominently (a blocking acknowledgement prompt in the widget, and a
  dashboard-level unacknowledged-count indicator) and the recipient must click
  the sign-off affordance to clear it. `isCompulsory` already prevents removal;
  this adds the read-gate on top.
- Acknowledging writes a receipt server-side: `(announcementKey, userId,
  contentVersion, acknowledgedAt)`. Writes are **idempotent** — re-clicking or
  a double request never produces a second row for the same
  `(announcementKey, userId, contentVersion)`.
- When `reacknowledgeOnChange` is set and the author bumps
  `acknowledgementContentVersion`, the item returns to the unacknowledged state
  for everyone until they acknowledge the new version; prior-version receipts
  are retained as history.

### Read-receipt report (admin)

- An admin / template owner can open a **read-receipt report** for an
  announcement that returns, for the current content version: acknowledged
  count, pending count, the list of pending user ids, and each acknowledgement's
  timestamp. The audience is resolved from the template's group routing
  (`admin-templates`) via `IGroupManager` at report time, and pending =
  (current audience) − (users with a receipt for the current version).
- The report is exportable (CSV) for the compliance file and each
  acknowledgement raises an entry in the existing Activity feed
  (`activity-feed-integration`) so the audit trail is uniform with the rest of
  LaunchPad.

### Data + authorization

- One new local table `oc_launchpad_acknowledgements` — consistent with the
  five tables LaunchPad already owns; keyed to be idempotent. No OpenRegister
  install-time dependency is introduced (`launchpad-adopt-or-abstractions`).
- Endpoints enforce ADR-005 authorization: a recipient may read/write **only
  their own** receipt (no IDOR on `userId`); only an admin / template owner may
  set the requirement or read the aggregate report; the report exposes no PII
  beyond user id + timestamp.

## Capabilities

### New Capabilities

- `dashboard-acknowledgements` — mandatory-read acknowledgement requirement on
  widget placements, forced-delivery read-gate, idempotent per-user receipts,
  re-acknowledge-on-change, and an admin read-receipt report scoped to the
  announcement's audience.

### Modified Capabilities

(none — the requirement is expressed as additive placement fields; existing
`widgets` / `admin-templates` behaviour is unchanged when
`requiresAcknowledgement = 0`)

## Impact

**Affected code (indicative — implemented in a later apply pass):**

- `lib/Db/Acknowledgement.php`, `lib/Db/AcknowledgementMapper.php` — new local entity + mapper
- `lib/Migration/VersionXXXX` — creates `oc_launchpad_acknowledgements`
- `lib/Db/WidgetPlacement.php` — additive acknowledgement fields on the placement
- `lib/Service/AcknowledgementService.php` — receipt write (idempotent), report aggregation, audience resolution via `IGroupManager`
- `lib/Controller/AcknowledgementController.php` — `POST /api/acknowledgements`, `GET /api/acknowledgements/report/{announcementKey}`, `GET /api/acknowledgements/pending`
- `lib/Activity/Extension.php` — one new activity event (`dashboard_acknowledged`)
- `src/components/**` — forced-delivery acknowledgement prompt on compulsory widgets, dashboard unacknowledged indicator, admin read-receipt report view

**Affected capabilities / integrations:**

- `widgets`, `admin-templates` — read the new placement fields (behaviour unchanged when unset)
- `activity-feed-integration` — surfaces the acknowledgement event
- `launchpad-compliance-audit-panel` — differentiated (per-user dismissal, not org read-receipt); vocabulary reused, no overlap
- No OpenRegister install-time dependency (`launchpad-adopt-or-abstractions`)
