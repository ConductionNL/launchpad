---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# MCP & AI Integration

## Overview

Directus has recently added AI features including a built-in MCP (Model Context Protocol) server, AI chat interface, and configurable AI providers. This positions Directus as an AI-ready data platform where LLMs can interact with structured data.

## MCP Server

Directus exposes an MCP endpoint at `/mcp` that allows AI agents to:
- Discover available collections and their schemas
- Perform CRUD operations on data
- Access prompts stored in a configurable collection

### Configuration
The MCP server is controlled via global settings:
- `mcp_enabled` - Enable/disable the MCP server
- `mcp_allow_deletes` - Whether AI agents can delete data
- `mcp_prompts_collection` - Collection storing reusable prompts
- `mcp_system_prompt` - System prompt for AI interactions
- `mcp_system_prompt_enabled` - Enable/disable system prompt

### Authentication
MCP requests go through the standard authentication middleware, meaning AI agents operate with the permissions of the authenticated user.

### Implementation
The `DirectusMCP` class wraps the MCP protocol handler:
```typescript
const mcp = new DirectusMCP({
  promptsCollection: 'ai_prompts',
  allowDeletes: false,
  systemPromptEnabled: true,
  systemPrompt: 'You are a helpful data assistant...',
});
mcp.handleRequest(req, res);
```

## AI Module Structure

The AI module (`api/src/ai/`) contains:
- **`chat/`** - Chat interface backend
- **`files/`** - AI file processing
- **`mcp/`** - MCP protocol handler
- **`providers/`** - AI provider integrations (OpenAI, Anthropic, etc.)
- **`tools/`** - AI tool definitions

## AI Providers

Recent migrations (20260110A) add provider-specific settings, suggesting support for:
- Multiple AI provider configurations
- Per-provider API keys and settings
- Provider selection at the application level

## AI Settings

The settings migration (20251103A) adds AI configuration to the global settings singleton, indicating AI is treated as a first-class platform feature.

## Relevance to OpenRegister

OpenRegister also has a built-in MCP server, making this a direct feature overlap:

| Feature | Directus MCP | OpenRegister MCP |
|---------|-------------|-----------------|
| Protocol | MCP standard (JSON-RPC 2.0) | MCP standard (JSON-RPC 2.0) |
| Transport | HTTP POST | HTTP POST (Streamable HTTP) |
| Auth | Standard Directus auth | Nextcloud auth (Basic/Bearer) |
| CRUD | Full CRUD on all collections | Full CRUD on registers/schemas/objects |
| Delete control | Configurable `mcp_allow_deletes` | Not configurable |
| Prompts | From configurable collection | Not implemented |
| System prompt | Configurable | Not implemented |
| AI chat | Built-in chat UI | Not built-in |
| Resources | Not documented | `openregister://` URIs |
| Discovery | Via MCP tools | Via MCP resources + REST discovery |

OpenRegister's MCP implementation could be enhanced with:
1. Configurable delete permissions for AI agents
2. A prompts collection for reusable AI interactions
3. System prompt configuration in app settings
4. An AI chat interface in the admin UI
