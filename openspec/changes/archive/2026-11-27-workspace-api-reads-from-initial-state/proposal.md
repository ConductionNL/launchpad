# Proposal: workspace-api-reads-from-initial-state

## Why
After `workspace-boot-reads-from-initial-state` and `dashboard-first-load-from-initial-state` the Workspace still sent five launchpad GETs as soon as its view mounted: the notice banner (`/api/announcements`, `/api/preferences/dismissed-notices`), the active dashboard's reactions and, on a dashboard somebody else owns, the viewer's personal layer, plus the first page of every Files widget. Each costs a full PHP request (session, app boot, ~40-60 ms CPU on the reference stack) and the view renders a second time when they come back.

## What
The page answers those reads while it renders, through the very controller actions the browser would call, and carries them as `workspaceApiReads` (`[{path, params, data}]`). A one-shot interceptor on the app's HTTP client answers the first matching GET of each from the page; every later GET goes out as before. A read that is refused, fails or answers anything but 200 is not carried, so the browser asks and meets the same answer.

## Impact
- Workspace: 8 -> 3 API requests at load (only core's user_status, contacts menu teams and notifications remain).
- Server: five PHP requests per Workspace view fewer; the page render itself takes ~20 ms longer.
