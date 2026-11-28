---
kind: code
---

# Proposal: more-menu-in-page-header

## Why

The pixel pass of 27 November 2026 (board LpStore) found the More menu outside the content column on every page but
the dashboard view: `App.vue` placed it absolutely at the top end of the content. Board LpStore draws it as the last
button of the page header's action row, beside "Dashboard publiceren".

## What changes

- `runtime-shell` REQ-SHELL-009: on a page whose header draws an action row, More joins that row as its last button.
  A page without such a row keeps it at the top end of the content, as before.
