# Design — OpenRegister Leaf Integrations (Talk on Shared Dashboards)

## Context

OpenRegister's integration leaves are adopted declaratively (`configuration.linkedTypes` on a schema). LaunchPad has a single `Dashboard` schema and currently adopts zero leaves. The design question for this change was not "how do we build a discussion feature" — the leaf and Talk own that — but three scoping decisions:

1. Which leaves, if any, honestly fit a schema whose objects are layout configurations rather than domain records?
2. Where does the leaf declaration live — base register file or a `register.d` overlay?
3. What gates the discussion surface — a new ACL, or the existing dashboard view guard?

## Goals / Non-Goals

**Goals:**

- Adopt the talk leaf for shared and group dashboards with zero bespoke chat code.
- Keep the declaration additive and overlay-based so the base register file stays a clean statement of the domain.
- Reuse `PermissionService::canViewDashboard()` as the single authorization truth for discussion access.
- Document explicitly why the remaining ~18 leaves are not adopted, so future sweeps do not re-litigate this.

**Non-Goals:**

- Building comments, mentions, or notification features in launchpad — the leaf renders, Talk hosts.
- Adopting the files leaf, mail sidebar linking (`configuration.linkedTypes: ["mail"]`), or `mailObjectTemplate` — see D1.
- Per-widget or per-tile discussions — the conversation unit is the dashboard object.

## Decisions

### D1: Adopt exactly one leaf — talk. Every other leaf is scoped out.

**Decision**: Declare only `"talk"` in `linkedTypes`. No files, no mail, no calendar/contacts/maps/etc.

**Alternatives considered:**

- **Adopt files + talk** (the two "generic collaboration" leaves): rejected for files because LaunchPad already owns a resource pipeline for dashboard assets (`ResourceController`/`ResourceService`, REQ-RES-001..014, routes `/api/resources*`) and a files widget for user storage. A generic attachments panel on a layout-configuration object duplicates that machinery with no user story behind it.
- **Adopt the full catalogue for uniformity with sibling apps**: rejected — sibling apps (larpingapp, pipelinq, shillinq) attach leaves to schemas describing people, events, contracts. `Dashboard` describes none of these; declaring leaves that render meaningless panels is padding, and an unused surface is an unaudited surface.

**Rationale**: The one place LaunchPad has real multi-user collaboration around a single object is shared/group dashboards (manifest features "Dashboard sharing", "Group dashboards"; `sharedWith`, `type: group_shared`, `groupId` in the schema; the whole REQ-SHARE surface). A conversation about *this dashboard* is the only leaf-shaped gap. The schema's own `"mailEnabled": false` confirms mail was already considered and rejected at the domain level.

### D2: Declaration lives in a `register.d` overlay, not the base register file

**Decision**: New file `lib/Settings/register.d/dashboard-talk-leaf.json` containing only the `configuration.linkedTypes` addition for the `Dashboard` schema.

**Alternatives considered:**

- **Edit `launchpad_register.json` directly**: rejected. The fleet pattern (larpingapp's `register.d/*-leaf.json` overlays) keeps each leaf adoption a self-contained, reviewable, revertable file, and keeps the base file a pure domain statement. LaunchPad's `register.d/` directory already exists (README only) precisely for this.

**Rationale**: Overlay files make the adoption diff one file, and removing the leaf later is deleting one file rather than surgically editing the 310-line base register.

### D3: Discussion access = dashboard view access; surface only on shared dashboards

**Decision**: The discussion surface renders only when the dashboard is collaborative (`group_shared`, or has active shares / non-empty `sharedWith`), and reaching it requires `PermissionService::canViewDashboard()` to pass. Share revocation ends access.

**Alternatives considered:**

- **Show discussion on every dashboard, including unshared personal ones**: rejected — a room with one member is noise, and it would create Talk rooms for thousands of personal dashboards nobody discusses.
- **Separate discussion ACL (opt-in per participant)**: rejected — a second ACL diverges from the dashboard's own access over time (the classic parallel-permission drift), and the view guard already encodes owner/share/group logic in one audited place.

**Rationale**: One authorization truth. `canViewDashboard()` already resolves ownership, user/group shares, and group membership; the leaf simply inherits its answer.

### D4: Hard fail nowhere — Talk absence degrades to absence of the surface

**Decision**: With Talk missing/disabled the affordance does not render and nothing errors. LaunchPad's manifest does not gain a Talk dependency.

**Rationale**: LaunchPad must keep booting and rendering dashboards on instances without Talk (ADR-083-style boot safety); an integration leaf is an enhancement, never a prerequisite.

## Risks / Trade-offs

- **Risk**: The leaf's render contract may evolve in OpenRegister; launchpad's wiring is intentionally thin so the blast radius is the overlay file plus one mount point.
- **Trade-off**: Users of unshared personal dashboards get no discussion surface at all. Accepted: share the dashboard first — that is the collaboration signal.
- **Risk**: Schema overlay import failing silently (see the cautionary note inside `launchpad_register.json` about the schema importer rejecting union types). Mitigated by an explicit import verification task.

## Migration Plan

None required. The overlay is additive; no data or existing-object changes. Rollback = delete the overlay file and re-import.

## Open Questions

- Whether the discussion mounts in the dashboard sidebar or the dashboard settings panel is left to the leaf's standard render surface — decided at implementation against the leaf's contract, not re-specified here.
