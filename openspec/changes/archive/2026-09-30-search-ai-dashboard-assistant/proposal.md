---
kind: code
depends_on: []
---

# Ask an assistant about your dashboard, answered through hermiq

## Why

Ruben decided on 29 Sep 2026 (DECISIONS row 16) to build the AI dashboard assistant, with the model coming through hermiq. The main spec `openspec/specs/launchpad-ai-dashboard-assistant/spec.md` (REQ-ADA-001 to 007) describes the widget but routes inference through an openconnector `local-llm` source, which is not how the fleet reaches a model today. hermiq is the fleet's chat engine: `POST /apps/hermiq/api/chat/stream` (SSE) with `POST /api/chat/send` as fallback, `GET /api/chat/health` as the presence probe (hermiq `appinfo/routes.php:518-549`, read at hermiq development), and `@conduction/nextcloud-vue` ships `useAiChatStream` and `aiChatConfig`, which already speak that contract. Nothing of the widget exists in launchpad (the change `launchpad-ai-dashboard-assistant` in `openspec/changes/` is an older, unrelated case-summary plan).

This change covers 1 row of the parity matrix, reversed from `decided-no` to `build` on 2026-09-29.

**a-assistant** (matrix `launchpad`, area `search-ai`), "Ask an assistant in plain language and get answers from the organisation's own content." `built.state` moves to `specified`.

## What changes

- A widget type `ai-assistant` ("Assistant") in the widget registry, the Add widget modal and the manifest.
- The widget sends the question through `useAiChatStream` to hermiq, with the dashboard's context (title, widget titles and types) as the `context` snapshot, and streams the reply in.
- When hermiq is not installed or its health probe fails, the input is disabled and says so; the rest of the dashboard keeps working.
- A small placement shows one "Summarise this dashboard" button instead of the chat.
- History lives in the browser session only; launchpad adds no backend route and no LLM client.

## Capabilities

### Modified capabilities
- `launchpad-ai-dashboard-assistant`: routes inference through hermiq instead of an openconnector source; the scope and tool rules follow hermiq's governed MCP transport.
