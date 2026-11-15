# Edit a shared dashboard together

## Why

Ruben decided on 13 Nov (decision 406) that the drawn board is canon. LpBewerken draws two
people editing one dashboard at once: "Marieke Jansen bewerkt dit dashboard ook sinds 10:42. Uw
wijzigingen worden niet overschreven", with "Wie werkt er mee?" and "Wijzigingen worden direct
bewaard". The built behaviour is a lock (`dashboard-locking`, REQ-LOCK-001 and REQ-LOCKUI-001):
the second editor stays read-only behind a banner. The lock stays as built until this change
lands.

### Matrix rows (launchpad `openspec/parity/capabilities.json`)

| row | what changes |
|---|---|
| `build-edit-lock` | several people edit a shared dashboard at once, see each other, and no one's change is lost |

## What changes

- Several people with edit rights edit one shared dashboard at the same time.
- Each editor sees who else is editing and since when.
- Every change is saved at once, per widget. A change to one widget never overwrites a
  colleague's change to another widget. Two changes to the same widget do not silently replace
  each other.
- A new widget lands on the first free spot, never over another widget.

## Impact

- The workspace page edit mode (`src/views/Views.vue`, `src/composables/useDashboardLock.js`,
  `src/components/Workspace/EditLockBanner.vue`), the dashboard save endpoint. The lock is
  replaced in edit mode once this is built. Not built yet: the matrix row moves to `specified`.
