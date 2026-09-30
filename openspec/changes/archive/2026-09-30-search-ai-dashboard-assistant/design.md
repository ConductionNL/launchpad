# Design: search-ai-dashboard-assistant

## Context (read at launchpad development 106fd259, hermiq development, nextcloud-vue development)

- hermiq `ChatController::sendMessage` reads `conversation`, `agentUuid`, `message`, `views`, `tools`, `context` and answers with the reply, `conversation`, `toolCalls` and `pendingApprovals`. `ChatStreamController::stream` is the SSE variant. Both are `NoAdminRequired` and guard the conversation per user.
- nextcloud-vue `useAiChatStream` streams from `/index.php/apps/{chatAppId}/api/chat/stream` and falls back to `/api/chat/send`; `aiChatConfig` probes `/api/chat/health`.
- Tool calls run inside hermiq over its governed MCP transport, scoped by OpenRegister RBAC, so launchpad does not filter tools itself.

## Decisions

### D1. hermiq replaces openconnector as the route (amends REQ-ADA-001, 003, 004, 005)
The content field `modelAlias` becomes `agentUuid` (optional; empty means hermiq's default agent). The soft requirement becomes `requires.apps: ['hermiq']`. `manifest.dependencies` does not list hermiq. Tool scoping and the read-only default are hermiq's job: launchpad passes `scope` in the context snapshot and never forwards a tool list.

### D2. The shared composable, no own client
The widget uses `useAiChatStream` from `@conduction/nextcloud-vue`. If the installed version lacks it, the task stops and names the version: no copy of the client goes into launchpad.

### D3. Prompts are strings, not code
The summary button sends a translated question ("Summarise this dashboard for me."), per REQ-ADA-004's rule that no system prompt is checked into source.
