# MyDash issue log

Running tracker of issues found in the **mydash** app. Lightweight on purpose: each entry records that the issue exists, where (symptom, request, error, rough area), and — when the evidence supports it — what the bug is. No proposed fixes.

---

## MD-001 — Setup wizard step 4 showcase images 404

- **App:** mydash
- **Status:** Open
- **Reported:** 2026-05-27

On step 4 the wizard requests showcase preview images and they all 404, leaving the showcase cards without images. The image files do exist in the app at `img/showcases/` (e.g. `de-bron.png`, `de-linden.png`, `gemeente-duin.png`, `horizon-labs.png`, `van-der-berg.png`), so the requested URL path does not match how the assets are served.

```http
GET https://nextcloud.test/apps/mydash/img/showcases/de-bron.png
```

Rough area: showcase image URL generation in the wizard frontend vs. the app's served asset path.

---

## MD-002 — Showcase install failure replaces the install cards (no retry possible)

- **App:** mydash
- **Status:** Open
- **Reported:** 2026-05-27

When a showcase install fails, the wizard replaces all of the install cards with a single message: *"Could not install showcase. Please try again."* Because the cards are gone, there is no way to actually retry from that screen despite the message asking the user to.

<img src="issue-log-screenshots/mydash-wizard-showcase-install-failed.png" alt='MyDash wizard showing "Could not install showcase. Please try again." in place of the install cards' width="600" />

Rough area: error-state rendering in the setup wizard frontend (demo data / showcases step).

---

## MD-003 — Organization navigation drag handle looks draggable but isn't

- **App:** mydash
- **Status:** Open
- **Reported:** 2026-05-27

The Organization navigation editor (admin settings) lets you build a nested tree of sections, links and children to any depth. That part works correctly — items can be created, customized, and saved without issues. The only problem is the reorder affordance: each row shows a `::` drag handle on the left and the cursor changes to a grab hand over it, signalling drag-to-reorder. Dragging does nothing — the row can't be moved and the `::` just gets selected as text.

This is a false affordance rather than a broken feature: drag-and-drop was never implemented. The handle (`.org-nav-row__handle` in `OrgNavigationEditorRow.vue`) is styled `cursor: grab` but carries no `draggable` attribute or drag listeners, so the browser falls back to selecting the glyph as text. Comments in `OrgNavigationEditor.vue` confirm reordering is intended to go through the up/down buttons (REQ-ONAV-007) with drag-and-drop deferred to a future change, yet the grab cursor and the panel's "Drag nodes to reorder" help text still advertise a capability that isn't there.

<img src="issue-log-screenshots/mydash-org-navigation-drag-handle.png" alt="MyDash Organization navigation editor showing the :: drag handle with a grab cursor that does not actually drag" width="600" />

Rough area: `src/components/admin/OrgNavigationEditorRow.vue` (handle styling), `src/components/admin/OrgNavigationEditor.vue` (help text / reorder model).

---

## MD-004 — Admin settings panels don't refresh after the setup wizard completes

- **App:** mydash
- **Status:** Open
- **Reported:** 2026-05-28

Finishing the setup wizard hides the wizard banner but leaves the rest of the admin settings page showing its pre-wizard state. The wizard writes data that other panels on the same screen are responsible for displaying — most visibly, step 3 activates groups, and once the wizard closes the "Group priority order" panel still reflects whatever it had loaded on page mount (typically empty on a fresh install), not the groups just selected. The same applies to any other panel whose data the wizard touches (default settings, etc.). A manual full-page reload makes the updated state appear, confirming the data was persisted — only the in-page views are stale.

The `AdminSettings.vue` completion handler (`onWizardCompleted`) re-fetches only `loadWizardState()` so the banner can disappear (per REQ-WIZ-002), but does not trigger the per-panel loaders (`GroupPriorityOrder.loadGroups()` and friends), so the rest of the page never re-syncs after the wizard mutates the backend.

Rough area: `src/components/admin/AdminSettings.vue` (`onWizardCompleted` handler — needs to re-trigger the panel loaders, or panels need to react to a wizard-completed event).
