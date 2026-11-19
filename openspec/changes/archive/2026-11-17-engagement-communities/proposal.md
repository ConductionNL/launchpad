---
kind: code
depends_on: []
---

# Join topic communities from the start page

## Why

Staff who want to ask a question outside their own team, or share an idea
with everyone who cares about a topic, have no way in from LaunchPad. Nextcloud
Talk already hosts open conversations anyone may join, but nobody finds them:
Talk's own dashboard widget lists only the conversations you are already in.

Matrix row **e-communities** (`openspec/parity/capabilities.json`), "Join topic
communities where staff ask questions and share ideas", rated `no`,
`built.state` `none`.

- Workspace 365, yes: https://portal.productboard.com/iqfsnhkpzih6grzjwagif2ek/tabs/3-launched launched "Communities now available for everyone!"; https://support.workspace365.net/en/articles/305049-using-communities-in-the-workspace-365-hub.
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/available-dashboard-cards Stay Engaged card: "access their Viva Engage feed conversations".

## What changes

- A new "Communities" widget lists the open Talk conversations the viewer may join, with name, description and number of members, and marks the ones they already joined.
- "Join" adds the viewer to the conversation in one click; "Open" goes to it in Talk.
- An administrator may pin a short list of conversations as the organisation's official communities; they show first with a mark.
- Without Talk the widget shows an empty state that says Talk is needed, and never an error.

## Capabilities

### New capabilities

- `communities-widget`: the Communities widget over Talk's open conversations.

## Impact

- New widget type `communities` in `src/constants/widgetRegistry.js` with a renderer and a small form (title, show only pinned)
- New admin setting `pinned_communities` (Talk conversation tokens) in `lib/Db/AdminSettingKey.php`
- Talk is called from the browser through its OCS API as the signed-in user; LaunchPad stores no conversation data.

## Out of scope

- Building discussion features inside LaunchPad. The open change `openregister-leaf-integrations` records that non-goal: Talk hosts discussion, LaunchPad shows the way in.
