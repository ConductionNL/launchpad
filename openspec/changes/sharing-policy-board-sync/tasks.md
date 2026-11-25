# Tasks: sharing-policy-board-sync

- [x] Sharing settings: "Nobody" beside Everyone and Only these groups (`sharing_allowed = none`); server check in the share create path returns 403 for non-admins
- [x] Admin setting `public_links_allowed` (default on); `DashboardSharingPolicy.vue` switch with the note that a link always has an expiry date
- [x] Public share create: refuse when public links are off or the caller may not share (403); refuse without `expiresAt` (400); the Sharing tab hides the public link block when off and marks the expiry date required
- [x] Admin setting `leaver_handover` (`{manager: bool, fallbackUid: string}`) and its control in the Opruimen block of the operations tab
- [x] User deletion: read the manager in a `BeforeUserDeletedEvent` listener, apply the handover for owned dashboards before REQ-SHARE-012, keep shares, notify the new owner
- [x] Translations in every shipped locale
- [x] Unit tests: nobody may share; public links off, on, expiry required; handover to manager, to fallback, unchanged without the setting
- [ ] (API half written in tests/e2e/sharing-policy.spec.ts, runs in nightly; the Sharing-tab UI half not yet) Playwright: with Nobody set the Sharing tab offers no add; a public link without expiry date is refused
