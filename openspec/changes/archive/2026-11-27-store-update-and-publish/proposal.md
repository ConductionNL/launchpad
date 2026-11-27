---
kind: spec
depends_on: [store-page-board-sync]
---

# Proposal: store-update-and-publish

## Why

Board LpStore draws two marketplace actions that store-page-board-sync left open: "Bijwerken" on a card whose register
version is newer than the installed one, and "Dashboard publiceren" in the page header. Both were product questions.
Decided on 23 November 2026 (product decisions delegated by Ruben):

1. **Bijwerken** updates the installed dashboard in place and keeps what people added themselves, the way an
   installed shipped template is updated (admin-templates REQ-TMPL-020) and re-synced (REQ-RESYNC-003).
2. **Dashboard publiceren** is for administrators only, to the one connected register.

## What changes

- `dashboard-store`: Update brings an installed dashboard to the register's newer version in place (REQ-STORE-012).
  REQ-STORE-006 still holds for Install: a first install creates, it never replaces.
- `dashboard-store`: an administrator publishes a dashboard to the connected register (REQ-STORE-013).

## Dependencies

- REQ-STORE-012 needs the install record of REQ-STORE-011 (change store-page-board-sync).
- REQ-STORE-013 needs OpenRegister's store plane to accept a publication: the open OpenRegister change
  `store-over-federated-config`. Its tasks are marked blocked until that lands; the builder SHALL NOT build a
  LaunchPad-side publish route of its own in the meantime.

## Impact

- `StoreController` and the store service (update and publish), the import path (update in place), the store page
  (Update and Publish dashboard actions), translations.
