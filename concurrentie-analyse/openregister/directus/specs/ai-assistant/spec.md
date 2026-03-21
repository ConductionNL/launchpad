# AI Assistant & MCP Integration

## Feature Summary
Directus provides two AI integration approaches: a built-in AI Assistant within the Data Studio, and an official MCP (Model Context Protocol) server for connecting external AI tools.

## How Directus Implements This

### Built-in AI Assistant
- Conversational assistant directly in the Data Studio UI
- No additional client setup required
- Assists with content editing, data management tasks
- Configurable AI model providers (OpenAI, Anthropic, etc.)
- Context-aware — understands current collection and item context

### MCP Server
- Official Directus MCP server package
- Compatible with Claude Desktop, ChatGPT, Cursor, and other MCP clients
- stdio transport for local usage
- Tools for CRUD operations on collections
- Custom prompts for common workflows
- Security configuration and access control
- Local MCP server option for development

### MCP Features
- **Tools** — Pre-built tools for interacting with Directus data
- **Prompts** — Template prompts for common tasks
- **Resources** — Expose Directus data as MCP resources
- **Security** — Configurable access scopes and permissions
- **Troubleshooting** — Built-in diagnostics

### AI Configuration
```
AI_ENABLED=true
AI_PROVIDER=openai
AI_API_KEY=sk-...
AI_MODEL=gpt-4
```

## OpenRegister Current State
OpenRegister has a basic MCP endpoint (`/api/mcp`) implementing the standard MCP protocol with JSON-RPC 2.0. It provides tools for registers, schemas, and objects. There is no built-in AI assistant in the UI.

## Gap Analysis

| Capability | Directus | OpenRegister |
|-----------|----------|-------------|
| Built-in AI Assistant | Yes | No |
| MCP Server | Official package | Basic implementation |
| MCP Tools | Comprehensive | registers, schemas, objects |
| MCP Prompts | Yes | No |
| MCP Resources | Yes | Basic |
| AI Model Config | Multi-provider | Not applicable |
| Content AI Features | Editing assistance | Not available |
| Local MCP | Supported | Not applicable |

## Competitive Impact
**Medium** — AI integration is increasingly expected. Directus's built-in assistant provides immediate value for content editors. However, OpenRegister's MCP implementation provides the foundation for external AI tool integration.

## Recommendation
OpenRegister should:
1. Enhance the MCP implementation with more tools and resources
2. Add MCP prompts for common workflows
3. Consider a built-in AI assistant via the Nextcloud AI ecosystem
4. Leverage Nextcloud's existing AI integrations (text generation, translation)
5. Document integration patterns with Claude Desktop and other MCP clients
