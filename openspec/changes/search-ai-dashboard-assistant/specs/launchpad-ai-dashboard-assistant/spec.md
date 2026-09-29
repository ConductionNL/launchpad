# Delta for launchpad-ai-dashboard-assistant: route through hermiq

## MODIFIED Requirements

### Requirement: REQ-ADA-004 Inference SHALL route exclusively through hermiq; launchpad MUST NOT contain an LLM client

Every question MUST go to hermiq's chat endpoints (`/apps/hermiq/api/chat/stream`, falling back to `/apps/hermiq/api/chat/send`) through the shared `useAiChatStream` composable. launchpad source MUST NOT import an LLM SDK, MUST NOT call a model URL directly, and MUST NOT carry a system prompt in code.

#### Scenario: Question streams through hermiq

- **GIVEN** hermiq is installed and healthy
- **WHEN** Pieter asks "Which of my cases are overdue?" in the assistant widget
- **THEN** the request goes to hermiq's chat stream with the dashboard context, and the reply appears as it streams

#### Scenario: hermiq absent

- **GIVEN** hermiq is not installed
- **WHEN** the widget mounts
- **THEN** the input is disabled with a message that the assistant needs hermiq, and the rest of the dashboard works

#### Scenario: No LLM client in launchpad

- **GIVEN** the launchpad `src/` tree
- **WHEN** scanned for `openai`, `@anthropic-ai`, `@ollama`, `llphant` imports or `localhost:11434`
- **THEN** there are no matches

### Requirement: REQ-ADA-005 Tool calls SHALL be governed by hermiq

launchpad MUST NOT pass a tool list. It MUST pass `scope` (`dashboard`, `workspace` or `tenant`) in the context snapshot; hermiq resolves and scopes tools over its governed MCP transport under OpenRegister RBAC.

#### Scenario: Scope travels in the context

- **GIVEN** a placement with scope `dashboard`
- **WHEN** a question is sent
- **THEN** the request's `context` carries `scope: 'dashboard'` and the dashboard's widget list, and no `tools` field
