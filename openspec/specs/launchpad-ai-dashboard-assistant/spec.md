---
status: done
---

# Spec: launchpad-ai-dashboard-assistant

**Status:** built (search-ai-dashboard-assistant, 29 Sep 2026)
**Scope:** launchpad
**Tier:** widget-capabilities
**Depends on:** widgets, widget-add-edit-modal, runtime-shell, initial-state-contract, permissions; cross-app runtime: hermiq (chat engine, `/apps/hermiq/api/chat/*`), reached through `@conduction/nextcloud-vue` `useAiChatStream`

## Purpose

An assistant widget (`ai-assistant`) lets a dashboard viewer ask plain-language questions about the dashboard and read the answer as it streams in. The widget is a thin chat surface: every question goes to hermiq, the fleet's chat engine, through nextcloud-vue's `useAiChatStream`. launchpad carries no model client, no model URL and no prompt in code. Tools the model may call run inside hermiq over its governed MCP transport, under OpenRegister RBAC, for the person asking.

Decided on 29 Sep 2026 (DECISIONS row 16, row `a-assistant`), replacing the earlier openconnector-source design.

## Requirements

@e2e exclude a live answer needs a running hermiq with a model on the instance; the widget is asserted in src/components/Widgets/Renderers/__tests__/AiAssistantWidget.spec.js and src/components/Widgets/Renderers/__tests__/noModelClient.spec.js, the registry in src/constants/__tests__/widgetRegistry.completeness.spec.js

### REQ-ADA-001: The system SHALL register an `ai-assistant` widget type

The widget MUST be registered in `src/constants/widgetRegistry.js` with `renderer`, `form`, `defaultContent`, `displayName` and `icon`, and listed in `lib/widget-types.json`, like every LaunchPad-rendered widget type.

#### Scenario: Widget registered and discoverable

- **GIVEN** the registry completeness test
- **WHEN** it runs
- **THEN** `ai-assistant` is in `lib/widget-types.json` and surfaces in `listWidgetTypes()` with a form and a renderer

### REQ-ADA-002: The widget content SHALL name only the hermiq agent

The placement MUST persist `{type: 'ai-assistant', content: {agentUuid}}`. `agentUuid` is optional; empty means hermiq's default agent.

#### Scenario: Default agent

- **GIVEN** a placement with `content: {agentUuid: ''}`
- **WHEN** a question is sent
- **THEN** the request carries `agentUuid: ''` and hermiq answers with its default agent

### REQ-ADA-003: There SHALL be no manifest entry for the widget

LaunchPad-rendered widgets are declared in the registry, not in `src/manifest.json` `widgets[]`; the assistant follows them. The manifest's `dependencies` MUST NOT list hermiq: hermiq's presence is checked at runtime (REQ-ADA-004).

#### Scenario: Manifest unchanged

- **GIVEN** `npm run check:manifest`
- **WHEN** it runs
- **THEN** it passes and the manifest names neither the widget nor hermiq

### REQ-ADA-004: Inference SHALL route exclusively through hermiq; launchpad MUST NOT contain an LLM client

Every question MUST go to hermiq's chat endpoints (`/apps/hermiq/api/chat/stream`, falling back to `/apps/hermiq/api/chat/send`) through `useAiChatStream` with `chatAppId: 'hermiq'` and the context `{appId: 'launchpad', pageKind: 'dashboard', objectUuid: <dashboard uuid>, route}`. launchpad source MUST NOT import an LLM SDK, MUST NOT call a model URL, and MUST NOT carry a system prompt in code.

#### Scenario: Question streams through hermiq

- **GIVEN** hermiq is installed and its health probe answers
- **WHEN** Pieter asks "Which of my cases are overdue?" in the assistant widget
- **THEN** the request goes to hermiq with the dashboard as context, and the answer appears as it streams

#### Scenario: hermiq absent

- **GIVEN** `GET /apps/hermiq/api/chat/health` does not answer 2xx
- **WHEN** the widget mounts
- **THEN** the input is not offered and the widget says the assistant needs hermiq; the rest of the dashboard works

#### Scenario: No LLM client in launchpad

- **GIVEN** the launchpad `src/` tree
- **WHEN** scanned for `openai`, `@anthropic-ai`, `@ollama`, `llphant` imports or a `localhost:11434` string
- **THEN** there are no matches

### REQ-ADA-005: Tool calls SHALL be governed by hermiq

launchpad MUST NOT pass a tool list. Which tools the model may call, and on what data, is decided by hermiq for the asking person over its governed MCP transport, under OpenRegister RBAC. `useAiChatStream` carries a fixed context shape, so a per-widget scope is not sent.

#### Scenario: No tools from launchpad

- **GIVEN** any question sent from the widget
- **WHEN** the request body is read
- **THEN** it carries `message`, `context` and `agentUuid`, and no `tools` field

### REQ-ADA-006: The widget SHALL render two reply modes — streamed chat and inline summary

When the placement is large enough (≥6 grid cells), the widget MUST
render a full chat interface (input box + scrollable history +
streamed response). When the placement is smaller (`<6` cells), it
MUST render in **summary mode**: a single tap-to-refresh "Summarise
this dashboard" call that uses a translated question ("Summarise this dashboard for me.") as the prompt and
fills the cell with the rendered Markdown reply.

#### Scenario: Large placement renders full chat

- **GIVEN** a placement with `gridWidth × gridHeight ≥ 6`
- **WHEN** the widget renders
- **THEN** an input box + conversation history MUST be visible
- **AND** the user MUST be able to submit follow-up turns up to
  `content.historyMaxTurns`

#### Scenario: Small placement renders summary mode

- **GIVEN** a placement with `gridWidth × gridHeight < 6`
- **WHEN** the widget renders
- **THEN** a single "Summarise" button MUST be visible alongside the
  most recent reply
- **AND** clicking the button MUST issue a fresh inference call

#### Scenario: Open cases summary acceptance (Specter source)

- **GIVEN** a case worker with open cases on the dashboard AND the
  widget in summary mode
- **WHEN** the viewer clicks Summarise
- **THEN** the widget MUST stream a reply whose first sentence
  names the total open count
- **AND** each case row in the reply MUST show identifier,
  status, and last-updated date (sourced from procest GraphQL via
  the MCP tool call)

#### Scenario: Consultation responses summary acceptance (Specter source)

- **GIVEN** a consultation has received responses AND the widget
  is in summary mode
- **WHEN** the viewer triggers a summary
- **THEN** the reply MUST surface total responses + response
  breakdown without the viewer needing to navigate away

### REQ-ADA-007: History SHALL persist on the placement, not on a launchpad backend

Conversation turns MUST persist only inside the Vue component's
session-local state. launchpad MUST NOT add a backend table or endpoint
to store chat history — the workspace already carries no chat
history surface, and adding one would create the install-time
dependency this spec exists to avoid.

#### Scenario: History scoped to session

- **GIVEN** a chat with 3 turns
- **WHEN** the user reloads the page
- **THEN** history MUST be empty after reload
- **AND** the widget MUST NOT issue any history-load API call

#### Scenario: No backend persistence route added

- **GIVEN** the launchpad backend route table after this widget ships
- **WHEN** inspected
- **THEN** zero routes matching `chat/history`, `ai/conversation`,
  or `assistant/messages` MUST exist

## Non-Functional Requirements

- **Accessibility:** answers update inside an `aria-live="polite"` region; the question input has a label and is reachable by keyboard.
- **Localisation:** every string, including the summary question, is in all 36 locales.
- **Privacy:** which model answers, and where it runs, is hermiq's configuration; launchpad sends only the question and the dashboard context.
