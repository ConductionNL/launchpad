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

None (Ruben, 27 Nov 2026: no migration of the last-viewed dashboard). A person without an explicit Set as default
lands on Vandaag or their group default after the upgrade; that includes members whose template copy was stored as
their last-used dashboard. Their copy stays and is one click away in the More menu. Copies made after the upgrade are
stored as the start dashboard (D2).

## Risks

- People used to "launchpad remembers where I was" now land on Vandaag. That is the decision. The More menu's
  favourites (change `more-menu-favourites`) make the way back one click.
- The effective default marker (REQ-EDM-002 follows the resolver) moves with D1; its unit tests need the new order.
