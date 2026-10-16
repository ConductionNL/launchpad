# Design: cross-app attention feed

## Decision 1: a data file, not a PHP interface

The brief preferred reading a declaration the apps already have. They have one, but only inside their JavaScript bundle (see the proposal), so LaunchPad cannot reach it. The next smallest thing is a file.

`appinfo/attention.json` was chosen over a PHP interface or an event because:

- it needs no code in the app, so an app cannot break LaunchPad at load time and LaunchPad needs no class from the app (`class_exists` lookups go quiet when an app id moves);
- it is the same data the app already wrote for its own card, so a test in the app can hold the two equal;
- LaunchPad finds it through `IAppManager`, which already knows which apps are enabled for which user.

## Decision 2: the browser counts, as the user

LaunchPad's server does not query OpenRegister for another user's data. The widget asks OpenRegister directly, with the user's session: `GET /apps/openregister/api/objects/{register}/{schema}?<filter>&_limit=1` and reads `total`. This is the request the apps' own cards make (`readVisibleWhenValue` in the shared library), so the number is the same number, and OpenRegister's access rules decide what the user may count.

## Decision 3: the link is built from the count's filter

A lesson from all four app lanes: a number and the list it links to drift apart unless they share one filter. The declaration has no separate link query. LaunchPad writes the filter into the link as the query string, tokens unresolved (`assignee=@me`), which is what the apps' own links carry. The count resolves the tokens first. One filter, two uses.

A filter must be flat for this: an operator is written in the key (`"deadline[lt]": "@today+1d"`), never nested (`"deadline": {"lt": ...}`). A nested filter is refused as an invalid declaration. The app lanes hit the nested form as a live defect.

## Decision 4: failure is shown

Three different things must not look alike:

| What happened | What the employee sees |
| --- | --- |
| Every count ran and none needs attention | "Nothing needs your attention right now." |
| A count needs attention | A line with the number, the reason, the app and a link |
| A count could not run (network, server error, no access, unknown token) | A small line under the list: "Could not check: {app}" |
| No app declares anything | "None of your apps reports attention items yet." |

When one count fails and the others are clear, the widget shows the failure and does NOT show "nothing needs your attention".

## Decision 5: ranking

1. Severity: `error`, then `warning`, then `info`.
2. Then the higher count.
3. Then the app's name, then the item id, so the order is stable.

The widget shows the first `limit` lines (default 5) and says how many more there are.

## The declaration

```json
{
  "version": 1,
  "items": [
    {
      "id": "cases-past-deadline",
      "title": "Cases past their deadline",
      "reason": "{value} of your cases are past their deadline or end today.",
      "severity": "error",
      "source": {
        "register": "dossiq",
        "schema": "case",
        "filter": { "assignee": "@me", "isFinalStatus": false, "deadline[lt]": "@today+1d" }
      },
      "op": "gt",
      "value": 0,
      "action": { "label": "Open these cases", "path": "/cases" }
    }
  ]
}
```

- `title`, `reason` and `action.label` are English source strings. LaunchPad translates them with the declaring app's own translations, so the app keeps its wording. `{value}` in `reason` is the count.
- `action.path` is a path inside the declaring app. LaunchPad prefixes `/apps/<app id>`. A path cannot leave the app.
- `op` is `gt`, `gte`, `lt`, `lte`, `eq` or `neq`; default `gt`. `value` defaults to 0.
- Tokens the count resolves: `@me`, `@now`, `@today`, `@today+Nd`, `@today-Nd`, `@monthStart`, `@quarterStart`, `@yearStart`. Any other `@` token fails that count, visibly.

## What each app adds

Written from each app's `src/menu-layout.simple.json` on 5 October 2026. The paths are the apps' own list routes (all four use history routing under `/apps/<id>`).

**dossiq** `appinfo/attention.json`

```json
{ "version": 1, "items": [ {
  "id": "cases-past-deadline",
  "title": "Cases past their deadline",
  "reason": "{value} of your cases are past their deadline or end today.",
  "severity": "error",
  "source": { "register": "dossiq", "schema": "case", "filter": {
    "assignee": "@me", "isFinalStatus": false, "statusHiddenInLists": false, "isDraft": false, "deadline[lt]": "@today+1d" } },
  "action": { "label": "Open these cases", "path": "/cases" }
} ] }
```

**pipelinq** `appinfo/attention.json`

```json
{ "version": 1, "items": [ {
  "id": "tickets-past-deadline",
  "title": "Tickets past their deadline",
  "reason": "{value} of your tickets in progress are past their deadline or end today.",
  "severity": "error",
  "source": { "register": "pipelinq", "schema": "ticket", "filter": {
    "assignee": "@me", "status": "in_progress", "slaDeadline[lt]": "@today+1d" } },
  "action": { "label": "Open these tickets", "path": "/tickets" }
} ] }
```

**decidiq** `appinfo/attention.json`

```json
{ "version": 1, "items": [ {
  "id": "decisions-open-for-voting",
  "title": "Decisions wait for the vote",
  "reason": "{value} decisions are open for voting.",
  "severity": "warning",
  "source": { "register": "decidiq", "schema": "decision", "filter": { "lifecycle": "voting" } },
  "action": { "label": "Open these decisions", "path": "/decisions" }
} ] }
```

**learniq** `appinfo/attention.json`

```json
{ "version": 1, "items": [ {
  "id": "attendance-flags-open",
  "title": "Attendance flags nobody has picked up",
  "reason": "{value} attendance flags are still open. Open one to start the follow-up.",
  "severity": "warning",
  "source": { "register": "learniq", "schema": "attendance-flag", "filter": { "lifecycle": "open" } },
  "action": { "label": "Open the flags", "path": "/attendance/flags" }
} ] }
```

Each app also needs:

1. the three strings of each item in its `l10n/en.json` and `l10n/nl.json` (the title is there already; `reason` and the action label were added by hand for the card and may be there too);
2. a test that the item's `source.filter` equals the filter of its "First today" card in `src/menu-layout.simple.json`;
3. the file in its release package (`appinfo/` ships already).

Two things for the owners to decide, not decided here: decidiq's and learniq's items count for everybody, not for "me" (their cards do the same), and their severity is set to `warning` here while their own cards use the error colour.

## What was considered and not done

- **Bundling the four declarations in LaunchPad.** It would make the widget show something before the apps add their file. It would also put a copy of each app's filter in LaunchPad, and copies drift. Not done.
- **Reading `src/menu-layout.simple.json` from the app's folder on disk.** `src/` is not part of every release package, and the file's shape is the shared library's to change.
- **A server-side count.** LaunchPad would have to ask OpenRegister on the user's behalf and cache per user. The browser already holds the session.
