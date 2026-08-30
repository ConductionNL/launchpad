# Re-syncing an admin template

When an admin template is distributed, each targeted user receives an
**independent personal copy**. That independence is what lets people
personalise their dashboard — but it also means that, without this feature,
correcting a template only ever reached *future* first-logins. A functioneel
beheerder who fixed a wrong link in the Burgerzaken template still had 40
colleagues looking at the old one.

Re-sync closes that gap: it pushes an updated template out to copies that
already exist.

## The two strategies

| Strategy | What happens to the template's widgets | What happens to the user's own widgets |
|----------|----------------------------------------|----------------------------------------|
| **Merge** (default) | Updated to match the template | **Kept** |
| **Overwrite** | Replaced wholesale with the template layout | **Removed** |

Use **merge** for routine corrections — a changed link, a new compulsory
announcement — so nobody loses the shortcuts they added. Use **overwrite**
only when you genuinely intend to reset a department to the standard layout,
and tell people first.

Compulsory widgets are reconciled under **both** strategies: a widget the
template pins cannot be missing from a copy after a re-sync.

## Always dry-run first

The action supports `dryRun`, which reports exactly which copies would change
and what would happen to each — **without mutating anything**. Run it, read
it, then run for real. This is the difference between "I think this is safe"
and "I know what this will do to 40 people's screens."

```http
POST /apps/launchpad/api/admin/templates/{id}/resync
{ "strategy": "merge", "dryRun": true }
```

## What else happens

- The operation is **idempotent** — running it twice produces no further change.
- Each run writes an **audit record** (who, what, when).
- Affected users are **notified**.
- For large target groups the work is handed to a background job rather than
  blocking the request.

## Permissions

Admin-only, guarded both by the `AuthorizedAdminSetting` attribute and an
explicit in-body admin assertion.

## Known limitation

Notifications are delivered via Nextcloud's `INotification` — the app's
existing (and only) notification pattern. The `x-openregister-notifications`
dialect branch is not wired in; see the archived change's `tasks.md`.

## Related

- [Admin Templates](admin-templates.md) — authoring and distributing templates.
- [Permission Levels](permissions.md) — what a copy's permission level allows.
