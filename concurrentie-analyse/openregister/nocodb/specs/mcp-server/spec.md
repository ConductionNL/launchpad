---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# MCP Server

## Overview

NocoDB includes a built-in MCP (Model Context Protocol) server that enables AI tools to interact with NocoDB data. It uses the StreamableHTTP transport (same as OpenRegister) and provides per-base scoped tokens.

## Architecture

### Transport
- StreamableHTTP via `@modelcontextprotocol/sdk/server/streamableHttp.js`
- Each request creates a new `McpServer` instance (stateless)
- Session ID generator disabled (stateless mode)

### Authentication
- MCP tokens created per base per user
- Token stored in `nc_mcp_tokens` meta table
- Token scoped to workspace + base
- Regeneratable with `nanoid(32)`

### Role-Based Access
- Uses `hasMinimumRole(user, ProjectRoles.EDITOR)` check
- Editor+ users can write (create, update, delete records)
- Viewer/Commenter users can only read

## Tools

### getBaseInfo
- Fetch information about the current base
- Read-only, idempotent
- No parameters needed

### listTables
- List all tables in the base
- Returns table metadata

### readRecords
- Read records from a table
- Supports where conditions and aggregation
- Pagination support

### createRecords
- Create new records in a table
- Editor+ permission required

### updateRecords
- Update existing records
- Editor+ permission required

### deleteRecords
- Delete records from a table
- Editor+ permission required

## Token Management UI

Account > MCP Server page shows:
- List of active MCP servers with Name, Base, Created On
- Create new token per base
- Regenerate token
- Delete token

## Key Files
- `mcp/mcp.service.ts` — MCP request handler and tool registration
- `mcp/mcp.controller.ts` — HTTP endpoint
- `mcp/descriptions.ts` — Tool parameter descriptions
- `services/mcp.service.ts` — Token CRUD operations (McpTokenService)
- `models/MCPToken.ts` — Token model

## Comparison with OpenRegister MCP

| Feature | NocoDB MCP | OpenRegister MCP |
|---------|-----------|-----------------|
| Transport | StreamableHTTP | StreamableHTTP |
| Auth | Per-base MCP tokens | Basic Auth + session |
| Tools | 6 (CRUD + meta) | 3 (registers, schemas, objects) |
| Scope | Per-base | Global |
| Session | Stateless (new server per request) | Stateful (session ID) |
| Role check | Editor+ for writes | Nextcloud permissions |

## Relevance to OpenRegister

1. Both use the same MCP SDK and StreamableHTTP transport
2. NocoDB's per-base token scoping is more granular
3. NocoDB's stateless approach (new server per request) is simpler
4. OpenRegister's 3-tool design (registers, schemas, objects) is more semantic
5. NocoDB includes aggregation/where descriptions in tool parameters
