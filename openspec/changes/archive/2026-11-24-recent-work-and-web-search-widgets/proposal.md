# Proposal: recent-work-and-web-search-widgets

## Why

Two widgets the boards draw are built on development but have no requirement of their own; their code points at the
widgets spec as a whole.

- **Verder waar u was** (boards LpVandaag, LpStart): the reader's own recent work across apps, one row per thing,
  named by the thing, with the app and when. Built in #1613 as the `recent-work` widget type.
- **Zoeken op internet** (board LpWerkplek): a search field, the search engine, a Search button and the line that the
  results open in a new tab. Built in 7409fd00 as `content.layout: 'web'` of the `search` widget.

## What changes

- `widgets`: REQ-WDG-032 for recent work and REQ-WDG-033 for the internet search layout.

## Impact

- Spec only for behaviour that exists; the tasks move the code's `@spec` tags to the new requirements and close test
  gaps.
