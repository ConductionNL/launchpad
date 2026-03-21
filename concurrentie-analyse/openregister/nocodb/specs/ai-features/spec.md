---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# AI Features

## Overview

NocoDB integrates AI capabilities directly into its field type system, allowing users to create AI-powered columns and buttons without external configuration. This includes AI text generation, AI-triggered actions, and chat functionality.

## AI Field Types

### AI Text (LongText + AI Meta)
- Based on `LongText` UIType with `LongTextAiMetaProp` metadata flag
- Generates text content using AI prompts
- Prompt template can reference other column values
- Listed as "AI Text" / "AI Prompt" in field picker

### AI Button
- Based on `Button` UIType with `type: 'ai'` in colOptions
- Triggers AI action when clicked
- `ButtonActionsType.Ai` action type
- Displayed as "AI Button" in field picker

## AI Integration Architecture

### Integration Store
- `IntegrationsType.Ai` integration type
- Token usage tracking:
  - `input_tokens` — Input tokens consumed
  - `output_tokens` — Output tokens generated
  - `total_tokens` — Total token usage
  - `model` — AI model identifier
- Slot-based storage for integration-specific metadata

### AI Column Model
- `AIColumn` model (separate from `FormulaColumn` and `ButtonColumn`)
- Stores AI-specific configuration per column
- References integration settings

## Chat System

### Chat Sessions
- `ChatSession` model — Persistent chat conversations
- `ChatMessage` model — Individual messages in a session
- Chat panel in the UI (`useChatPanel` composable)

### Chat UI
- `components/chat/` — Chat panel components
- Accessible from the dashboard
- Conversation history per session

## Command Palette AI

### Document Search
- Ctrl+J opens document/AI search
- Search through documentation and data
- AI-powered responses

## Relevance to OpenRegister

1. **AI fields as column types** is an innovative approach — data and AI tightly coupled
2. **Token tracking** per integration provides cost visibility
3. OpenRegister could integrate AI via n8n workflows rather than field types
4. **Chat functionality** could be provided by Nextcloud Talk integration
5. NocoDB's AI is built-in; OpenRegister's approach would be more modular via ExApps
