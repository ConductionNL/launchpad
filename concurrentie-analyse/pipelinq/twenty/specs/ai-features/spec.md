---
competitor: twenty
analyzed_date: 2026-03-14
feature: AI Features
category: ai
maturity: planned
---

# AI Features

## Summary

Twenty is actively developing AI capabilities but they are not yet available in production. Two main features are planned: an AI Chatbot and AI Agents in workflows.

## Planned Features

### AI Chatbot
- Context-aware conversational assistant
- Access to workspace data for querying records and relationships
- Page-context awareness ("this company")
- Natural language querying without menu navigation
- Example uses: closing opportunities, stalled deals, interaction summaries

### AI Agents in Workflows
- Workflow action type for AI processing
- Capabilities: data enrichment, classification, summarization
- Multi-step autonomous task execution
- Customizable prompts for specific data processing
- Use cases: lead categorization, company enrichment, email drafts, opportunity scoring

### AI Skills (App SDK)
- Apps can define custom AI skills via `defineSkill`
- Skills are available to AI agents within the platform

## Access Control
- AI agents managed through existing RBAC permissions
- Per-object read/write access configurable per role
- Settings > Roles configuration

## Relevance to Pipelinq

**Twenty's AI direction:**
- Native AI integration planned (chatbot + workflow agents)
- RBAC-scoped AI access is smart design
- AI skills as app extensions is forward-thinking

**Pipelinq/OpenRegister advantages:**
- MCP (Model Context Protocol) already implemented and working
- MCP enables any AI model/client to interact with CRM data
- n8n AI nodes provide immediate AI workflow capabilities today
- No vendor lock-in on AI provider (MCP is model-agnostic)
- OpenRegister's MCP is production-ready vs Twenty's planned state
