# Design: start-page-stays-vandaag

## Decisions

### D1. The start dashboard is a choice, the last-used dashboard is not

Ruben (27 Nov 2026): "the start page is Vandaag unless the user chose another start dashboard". The only choice a
person makes is Set as default in the More menu, which stores `default_dashboard_uuid`. The resolution chain therefore
reads that key first. `active_dashboard_uuid` (REQ-DASH-019) stays written when a person switches, so nothing that
reads it breaks, but the page chain no longer reads it.

New chain for the workspace page:

1. the chosen start dashboard (`default_dashboard_uuid`), when the person can still see it;
2. the template rung (REQ-TMPL-019);
3. the default dashboard of the person's primary group;
4. the default dashboard of the `default` group (Vandaag on every install since launchpad#2207);
5. to 8. the fall-backs as today (first group dashboard, first `default` dashboard, first personal dashboard, empty
   state).

A chosen start dashboard the person can no longer see is cleared, as a stale last-used preference is today.

### D2. A template copy becomes the start dashboard

REQ-TMPL-019 stored the copy as last-used. With D1 that would stop working on the next visit. The copy is now stored
as the start dashboard instead. That is the administrator's choice for the group, and the person can change it with
Set as default like any other choice.

### D3. A deep link still wins

`/apps/launchpad/<slug>` and `/apps/launchpad/dashboards/<id>` open the named dashboard; the chain only decides what
`/apps/launchpad/` opens.

## Migration

One repair step, idempotent:

- for each user with an `active_dashboard_uuid` that names a personal dashboard made from an admin template
  (the copy carries its template's id) and without a `default_dashboard_uuid`: set `default_dashboard_uuid` to that
  copy.
- nobody else is touched. A person whose last-used dashboard was another dashboard opens on their group default or on
  Vandaag from now on; their dashboards stay as they made them.

## Risks

- People used to "launchpad remembers where I was" now land on Vandaag. That is the decision. The More menu's
  favourites (change `more-menu-favourites`) make the way back one click.
- The effective default marker (REQ-EDM-002 follows the resolver) moves with D1; its unit tests need the new order.
