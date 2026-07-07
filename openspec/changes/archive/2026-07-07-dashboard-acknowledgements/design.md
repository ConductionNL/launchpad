# Design — dashboard-acknowledgements

## Context

LaunchPad competes in the intranet / employee-portal segment where
"mandatory read + read receipt" is table-stakes (Staffbase Forced Delivery,
Simpplr Mandatory reads, Unily Read receipts and sign-off, Interact/Powell
Mandatory acknowledgement) and doubles as a compliance control for gemeenten
(policy attestation with an auditable per-employee trail). LaunchPad already
has `isCompulsory` (widget cannot be removed) but nothing that gates *reading*
or that *proves* it.

## Decisions

### D1 — Extend compulsory widgets, do not build a new widget engine

The requirement is expressed as additive fields on the existing widget
placement (`requiresAcknowledgement`, `acknowledgementPrompt`,
`acknowledgementDeadline`, `reacknowledgeOnChange`,
`acknowledgementContentVersion`, `announcementKey`). This keeps the feature
inside the ADR-049 widgets-as-config model and means any widget type (header,
text, news, image) can carry an acknowledgement — no bespoke "announcement
widget" and no parallel rendering path. When `requiresAcknowledgement = 0` the
placement behaves exactly as today.

### D2 — `announcementKey` is the aggregation identity, not the placement uuid

Admin templates clone one blueprint placement into N per-user placements, each
with its own uuid (`admin-templates`: `createDashboardFromTemplate()` copies all
placements). Keying receipts by the per-user placement uuid would make an
org-wide report impossible. Instead a stable `announcementKey` (UUID) is minted
on the **template** placement when acknowledgement is first required and copied
to every clone. All receipts and the report key off `announcementKey`, so one
announcement has one identity across all recipients.

### D3 — Local table, not OpenRegister

`launchpad-adopt-or-abstractions` mandates that LaunchPad stays installable and
runnable without OpenRegister and owns its own tables (it already owns five).
Acknowledgement receipts are LaunchPad-local operational data, so they live in a
new local table `oc_launchpad_acknowledgements`. No install-time OR dependency
is added. (If a deployment later wants receipts in OR for cross-app reporting,
that can be an OPTIONAL runtime delegation, mirroring the optional
`permissions.delegate` hook in `launchpad-adopt-or-abstractions` — out of scope
here.)

### D4 — Idempotency via a unique key, not read-modify-write

Receipts carry a unique index on `(announcement_key, user_id, content_version)`.
The service treats a duplicate acknowledgement as success without a second
insert (REQ-ACK-003). This is race-safe (the DB enforces it) and keeps the
activity feed clean — only the first insert emits `dashboard_acknowledged`.

### D5 — Audience resolved live, pending computed by difference

The report resolves the audience from the template's group routing via
`IGroupManager` **at report time** (not a frozen snapshot), so a newly added
group member automatically shows as pending and a removed member drops out.
`pending = audience − acknowledged(currentVersion)`. This matches how the rest
of LaunchPad resolves group membership (`admin-templates`,
`role-feature-permissions`, `PermissionService`).

### D6 — Re-acknowledge-on-change keyed by content version

Authors bump `acknowledgementContentVersion` when the content materially
changes. With `reacknowledgeOnChange = 1`, receipts for prior versions are
retained as history but do not satisfy the new version, so everyone re-attests
(REQ-ACK-005). This is the compliance-critical path (a re-issued policy needs
fresh sign-off) and is opt-in to avoid nagging users on trivial edits.

## Explicit differentiation from `launchpad-compliance-audit-panel`

| | compliance-audit-panel `content.acknowledgements` | this change |
| --- | --- | --- |
| Scope | one widget's own deadline alerts | any compulsory placement |
| Identity | `deadlineId` inside one widget's content | stable `announcementKey` across all recipients |
| Direction | user dismisses (snooze) their own alert | user attests; admin gets a receipt |
| Admin view | none | read-receipt report + CSV, audience-scoped |
| Persistence | JSON sub-field on the widget | dedicated idempotent receipt table |

The two are complementary; this change does not modify the compliance panel and
reuses its "acknowledgement" vocabulary only.

## Authorization (ADR-005)

- Setting/changing/clearing the requirement: admin or template owner only (403 otherwise).
- Writing a receipt: authenticated user, own `userId` only — the endpoint ignores/rejects a body `userId` that is not the caller (no IDOR).
- Reading the report: admin or template owner only.
- Report payload: user id + timestamp + status only; no other PII.

## Non-goals

- No email/push transport of the acknowledgement request (delivery is on the
  dashboard; notification transport is owned by
  `2026-04-30-dashboard-sharing-followups` and NC's notification channels).
- No public/anonymous acknowledgement (receipts require an authenticated
  identity; published dashboards remain read-only per
  `public-dashboard-publication`).
