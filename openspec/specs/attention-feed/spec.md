---
status: done
---

# Attention feed specification

## Purpose

The attention feed is the "First today" list on a start page: what needs the employee's attention today, from all their apps, in one ranked list with a link into each app. An app says what needs attention in one data file. LaunchPad reads the files, the browser counts as the signed-in user, and the widget shows the result. A count that could not run is shown as failed, never as "nothing to do".

The contract and the reasons behind it are in `openspec/changes/cross-app-attention-feed/design.md`.

## Requirements

### Requirement: REQ-ATT-001 An app declares its attention items in a file

An app MAY ship `appinfo/attention.json`. LaunchPad MUST read that file for every app that is enabled for the signed-in user, and for no other app. LaunchPad MUST NOT require a PHP class, an interface or a registration call from the app.

The file MUST be a JSON object with `version: 1` and `items`, a list of at most 10 items. Each item MUST have:

- `id`: lower-case letters, digits and hyphens, unique within the file;
- `title`, `reason` and `action.label`: non-empty English source strings. `reason` MAY contain `{value}`, the count;
- `source.register` and `source.schema`: OpenRegister slugs;
- `source.filter`: optional, a FLAT object. A value is a string, number, boolean or a list of those. An operator is part of the key (`"deadline[lt]"`). A nested object as a value is invalid;
- `action.path`: a path inside the declaring app, starting with `/`, with no `..`, no `//`, no `?` and no `#`.

Each item MAY have `severity` (`error`, `warning` or `info`, default `info`), `op` (`gt`, `gte`, `lt`, `lte`, `eq` or `neq`, default `gt`) and a numeric `value` (default `0`).

LaunchPad MUST translate `title`, `reason` and `action.label` with the declaring app's own translations, in the user's language.

A file that is not valid MUST NOT take the feed down. The app MUST be reported under `invalid` with a reason, its valid items MUST NOT be offered in part, and every other app MUST still be offered.

#### Scenario: An app with a declaration is offered
- GIVEN dossiq is enabled for Pieter and ships `appinfo/attention.json` with the item `cases-past-deadline`
- WHEN Pieter's browser asks `GET /api/attention/sources`
- THEN the answer MUST hold one source with `appId: "dossiq"`, the app's display name, the item's id, its translated title, reason and action label, its source, `op: "gt"`, `value: 0` and `action.path: "/apps/dossiq/cases"`

@e2e exclude Needs an app that ships the file; no app does until the app lanes add it, and this change touches LaunchPad only. Pinned by AttentionSourceServiceTest::testAnEnabledAppWithADeclarationIsOffered, which reads a real file from a real app folder.

#### Scenario: An app that is not installed or declares nothing does not appear
- GIVEN pipelinq is not installed, and the Files app is enabled and ships no `appinfo/attention.json`
- WHEN the sources are collected
- THEN neither MUST appear under `sources`
- AND neither MUST appear under `invalid`

@e2e exclude Pinned by AttentionSourceServiceTest::testAnAppWithoutTheFileDoesNotAppear.

#### Scenario: An app that is disabled for this user does not appear
- GIVEN decidiq is enabled only for the group "bestuur" and Pieter is not in it
- WHEN Pieter's sources are collected
- THEN decidiq MUST NOT appear

@e2e exclude Pinned by AttentionSourceServiceTest::testOnlyAppsEnabledForTheUserAreRead.

#### Scenario: A broken declaration is reported and the others still work
- GIVEN dossiq ships a valid file and learniq ships a file whose filter nests an operator (`"deadline": {"lt": "@today"}`)
- WHEN the sources are collected
- THEN dossiq's item MUST be under `sources`
- AND learniq MUST be under `invalid` with a reason that names the item and the filter key
- AND no learniq item MUST be under `sources`

@e2e exclude Pinned by AttentionSourceServiceTest::testABrokenDeclarationIsReportedAndTheOthersStillWork and ::testEveryRuleOfTheDeclarationIsChecked.

#### Scenario: A link cannot leave the declaring app
- GIVEN an app declares `action.path: "/../settings/admin"` or `"//evil.example/x"`
- WHEN the sources are collected
- THEN the app MUST be reported under `invalid`

@e2e exclude Pinned by AttentionSourceServiceTest::testEveryRuleOfTheDeclarationIsChecked.

### Requirement: REQ-ATT-002 The sources endpoint

`GET /api/attention/sources` MUST return `{ sources: [...], invalid: [...] }` for the signed-in user. It MUST require a signed-in user and MUST NOT require an administrator. It MUST NOT run any count and MUST NOT return any object data: it returns what the apps declared.

#### Scenario: A signed-in employee gets the sources
- GIVEN Pieter is signed in
- WHEN his browser asks `GET /api/attention/sources`
- THEN the answer MUST be HTTP 200 with `sources` and `invalid`

@e2e exclude Pinned by AttentionControllerTest::testASignedInUserGetsTheSources. Not run in a browser.

#### Scenario: Nobody signed in
- WHEN a request without a session asks `GET /api/attention/sources`
- THEN the answer MUST be HTTP 401

@e2e exclude Pinned by AttentionControllerTest::testNobodySignedInGets401.

### Requirement: REQ-ATT-003 The count and the link come from one filter

For each source the widget MUST ask OpenRegister, as the signed-in user, for `/apps/openregister/api/objects/{register}/{schema}` with the source's filter and `_limit=1`, and MUST read `total` as the count.

Before the count is asked, the widget MUST resolve these tokens in filter values: `@me`, `@now`, `@today`, `@today+Nd`, `@today-Nd`, `@monthStart`, `@quarterStart` and `@yearStart`. A value that starts with `@` and is none of these MUST fail that source (REQ-ATT-005). It MUST NOT be sent as it is.

The link into the app MUST be the declared path with the SAME filter as its query string, tokens not resolved, so the app's list resolves them as it does for its own links. A list value MUST be written as repeated `key[]` parameters in both.

#### Scenario: The number and the list use the same filter
- GIVEN the dossiq source with the filter `assignee: "@me"`, `isFinalStatus: false`, `"deadline[lt]": "@today+1d"`
- WHEN the widget builds the count request and the link on 5 October 2026 for the user `pieter`
- THEN the count request's query MUST be `assignee=pieter&isFinalStatus=false&deadline[lt]=2026-10-06&_limit=1`
- AND the link MUST be `/apps/dossiq/cases?assignee=@me&isFinalStatus=false&deadline[lt]=@today+1d`
- AND the link's parameter names MUST equal the count's parameter names without `_limit`

@e2e exclude Pinned by attentionFeed.spec.js "the count and the link carry the same filter". Not run in a browser.

#### Scenario: An unknown token fails the source
- GIVEN a source whose filter holds `owner: "@manager"`
- WHEN the widget prepares the count
- THEN no request MUST be sent for that source
- AND the source MUST be shown as failed

@e2e exclude Pinned by attentionFeed.spec.js "an unknown token fails the source and sends nothing".

### Requirement: REQ-ATT-004 Merging and ranking

A source needs attention when its count compared with `value` by `op` is true. The widget MUST show the sources that need attention as one list across apps, ordered by severity (`error`, `warning`, `info`), then by the higher count, then by app name, then by item id.

Each line MUST show the title, the reason with the count filled in, the app's name and a link with the action label. The widget MUST show at most `limit` lines (widget setting, 1 to 10, default 5) and MUST say how many more there are.

#### Scenario: Items from three apps in one list
- GIVEN dossiq reports 3 (error), pipelinq reports 5 (error) and decidiq reports 2 (warning)
- WHEN the widget renders
- THEN the lines MUST be pipelinq, dossiq, decidiq, in that order
- AND the dossiq line MUST read "3 of your cases are past their deadline or end today." and link to dossiq

@e2e exclude Needs three apps that ship the file. Pinned by attentionFeed.spec.js "ranks by severity, then count, then app" and AttentionWidget.spec.js "shows one line per item that needs attention".

#### Scenario: A source that does not need attention is left out
- GIVEN learniq's count is 0 and its item says `op: "gt"`, `value: 0`
- WHEN the widget renders
- THEN no learniq line MUST be shown

@e2e exclude Pinned by AttentionWidget.spec.js "leaves out a source that does not need attention".

### Requirement: REQ-ATT-005 A failure is shown as a failure

A source fails when its count request does not answer with HTTP 2xx, when the answer has no numeric `total`, when the network fails, or when a token is unknown (REQ-ATT-003). A failed source MUST be named in a line under the list ("Could not check: {apps}"). An invalid declaration (REQ-ATT-001) MUST be named there too.

The widget MUST show "Nothing needs your attention right now." only when every source was checked and none needs attention. When any source failed, it MUST NOT show that sentence.

When no app declares anything, the widget MUST say so ("None of your apps reports attention items yet."). When the sources endpoint itself fails, the widget MUST say the list could not be loaded.

One failing source MUST NOT stop the other sources from being shown.

#### Scenario: One app fails and the others are clear
- GIVEN dossiq's count answers HTTP 500 and pipelinq's count is 0
- WHEN the widget renders
- THEN it MUST show "Could not check: dossiq"
- AND it MUST NOT show "Nothing needs your attention right now."

@e2e exclude Staging a failing OpenRegister answer needs request interception against an app that ships the file. Pinned by AttentionWidget.spec.js "a failed source is named and never reads as nothing to do".

#### Scenario: One app fails and another needs attention
- GIVEN dossiq's count fails and pipelinq's count is 5
- WHEN the widget renders
- THEN the pipelinq line MUST be shown
- AND "Could not check: dossiq" MUST be shown under it

@e2e exclude Pinned by AttentionWidget.spec.js "a failed source does not hide the others".

#### Scenario: Everything checked, nothing to do
- GIVEN two sources, both checked, both with count 0
- WHEN the widget renders
- THEN it MUST show "Nothing needs your attention right now."

@e2e exclude Pinned by AttentionWidget.spec.js "says nothing needs attention only when every source was checked".

#### Scenario: No app declares anything
- GIVEN the sources endpoint answers with no sources and no invalid declarations
- WHEN the widget renders
- THEN it MUST show "None of your apps reports attention items yet."

@e2e exclude Pinned by AttentionWidget.spec.js "says so when no app declares anything". This is also what the widget shows on an instance today, until an app ships the file; that was not looked at in a browser.

### Requirement: REQ-ATT-006 The widget type

LaunchPad MUST offer the widget as type `attention`, named "First today", in the add-widget picker. Its only setting is `limit`. It MUST be on LaunchPad's widget type list (`lib/widget-types.json`), so templates, showcases and imports can place it. The line colour MUST come from Nextcloud's theme variables, and severity MUST also be said in text for assistive technology, not by colour alone.

#### Scenario: The widget can be added
- GIVEN an employee edits a dashboard
- WHEN they open the add-widget picker
- THEN "First today" MUST be offered

@e2e exclude Not run in a browser. Pinned by widgetRegistry.completeness.spec.js, which holds the registry equal to lib/widget-types.json.
