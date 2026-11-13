## 1. Build

- [ ] 1.1 Presence: the edit mode publishes who is editing a dashboard and since when, and shows it ("Wie werkt er mee?", initials and the banner line).
- [ ] 1.2 Per-widget saves with a version per widget; a save on a stale widget version is refused and the editor sees the colleague's version.
- [ ] 1.3 Placement of a new widget on the first free spot, checked against the saved layout, never over another widget.
- [ ] 1.4 Replace the edit lock in edit mode once 1.1 to 1.3 work; keep the admin force-release for old locks until then.
- [ ] 1.5 Unit tests for two editors on different widgets, two editors on the same widget, and placement.

## 2. Hand-over

- [ ] 2.1 `@spec` tags and an e2e test or a reason-bearing `@e2e exclude` per scenario.
