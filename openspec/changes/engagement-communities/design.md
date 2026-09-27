# Design: engagement-communities

Read at development `d767c282`.

## Context

- Widget types register in `src/constants/widgetRegistry.js` (LaunchPad renderers such as `NewsWidget`, `PeopleWidget`; LaunchPad forms in `FORM_OVERRIDES` at line 123).
- Optional apps are detected at runtime, never required at install (`openspec/specs/launchpad-adopt-or-abstractions/spec.md`); `lib/Service/WeatherService.php:418` shows the `IAppManager::isEnabledForUser()` pattern, and `lib/Support/FleetAppId.php` handles fleet app ids.
- There is no Talk code in LaunchPad today. The open change `openregister-leaf-integrations` adopts Talk for dashboard discussion and lists "building comments, mentions, or notification features in launchpad" as a non-goal.
- Nextcloud Talk (`spreed`) exposes, over OCS for the signed-in user: `GET /ocs/v2.php/apps/spreed/api/v4/listed-room` (open conversations the user may see), `GET /ocs/v2.php/apps/spreed/api/v4/room` (the user's own conversations) and `POST /ocs/v2.php/apps/spreed/api/v4/room/{token}/participants/self` (join).
- Admin settings are typed keys in `lib/Db/AdminSettingKey.php`; the page gets them through `lib/Service/InitialStateBuilder.php`.

## Decisions

### D1: Communities are Talk's open conversations

The widget calls Talk's listed-room endpoint from the browser as the user, so Talk's own visibility rules decide what each person sees. LaunchPad adds no server proxy and stores nothing about conversations.

### D2: Talk presence is checked once at boot

`InitialStateBuilder` provides `talkEnabled` from `IAppManager::isEnabledForUser('spreed')`. When false, the widget renders the empty state "Communities need Nextcloud Talk. Ask your administrator to enable it." and makes no request.

### D3: Pinned communities come from one admin list

`pinned_communities` holds up to 20 Talk conversation tokens, validated as tokens (`[a-z0-9]{4,30}`). The widget shows pinned conversations first with a "Pinned by your organisation" mark, when the viewer may see them in Talk; the form's "Show only pinned" hides the rest.

### D4: Join, then open

"Join" posts to Talk's join endpoint and turns into "Open", which links to `/call/{token}`. Joined state comes from the user's own room list, fetched once per render.

## Declarative-vs-imperative decision

A widget over another app's API, rendered client-side; nothing to declare in a schema register, and no LaunchPad table.

## Permissions

Every user may place the widget on dashboards they may edit. Only administrators edit the pinned list. Talk enforces who may see and join each conversation.

## Test plan

- PHPUnit: the `pinned_communities` validation and the `talkEnabled` initial state.
- Vitest: listing with mocked Talk responses, pinned first, joined marks, join turning into open, the empty state without Talk.
- Playwright (Talk enabled in the test instance): an open conversation "Duurzaamheid" appears in the widget and one click joins it.
