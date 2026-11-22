# Proposal: wiki-widget

## Why

Board LpWerkplek (design-system #1192) puts a Wiki widget on a person's workplace: a search field, three favourite
pages and three recent pages from the organisation's XWiki. Board LpAfdeling puts a team wiki on the department page,
showing one space. Launchpad has no widget that reads the wiki.

## What changes

- A `wiki-pages` widget type, "Wiki" in the picker. It reads through OpenRegister's XWiki integration
  (`GET /apps/openregister/api/integrations/xwiki/search`). OpenRegister routes the call to the `xwiki` source of the
  connector app, which holds the wiki's address and credentials, so the browser never sees them.
- The person stars pages; their favourites live in their launchpad preference `wiki-favourites`.
- `content: { space, limit }`: one wiki space or all, and how many favourites and recent pages show.
- When OpenRegister answers 503 (no `xwiki` source, or the wiki is down) the widget says the wiki is not connected.
  Without OpenRegister it says OpenRegister is needed, and the picker does not offer the widget.
- A generic `integrationApps` initial-state key tells the picker which apps behind integration widgets are enabled for
  the viewer. A registry entry names its app with `requiresApp`.

## Decisions

- XWiki has no favourites of its own that the integration exposes, so favourites are the person's own, kept by
  launchpad. They follow the person across dashboards.
- "Recent" is what the integration lists for an empty query: the most recent pages the source returns.
