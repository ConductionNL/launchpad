---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# AI Features

## Summary

Baserow integrates generative AI at multiple levels: AI field type (premium), AI automation nodes, and AI workflow actions in the application builder. Supports multiple AI providers including OpenAI, Anthropic, Mistral, Ollama, and OpenRouter.

## AI Model Types

Located at `backend/src/baserow/core/generative_ai/`

### Supported Providers
1. **OpenAI** (`OpenAIGenerativeAIModelType`) - GPT models via OpenAI API
2. **Anthropic** (`AnthropicGenerativeAIModelType`) - Claude models
3. **Mistral** (`MistralGenerativeAIModelType`) - Mistral models
4. **Ollama** (`OllamaGenerativeAIModelType`) - Local models via Ollama (OpenAI-compatible)
5. **OpenRouter** (`OpenRouterGenerativeAIModelType`) - Multi-model router

### Configuration
- API keys configured at workspace or instance level
- Model selection per use case
- Provider-specific settings

## AI Field Type (Premium)

Located at `premium/backend/src/baserow_premium/fields/field_types.py`

- `AIFieldType` - Generates content using AI based on other field values
- Prompt template references other fields in the row
- AI-generated values stored as text
- Extends `SelectOptionBaseFieldType` for categorization use cases
- Computed on row save, not real-time

## AI Automation Nodes

- `AIAgentActionNodeType` - Use AI within automation workflows
- Process data, generate content, classify records
- Chain with other nodes for complex AI pipelines

## AI Builder Workflow Actions

- `AIAgentWorkflowActionType` - Invoke AI from application builder
- Trigger AI processing from user interactions
- Use AI results in UI elements

## AI Integration Type

- `AIIntegrationType` in integrations module
- Configures AI provider connection
- Shared across all AI-consuming features

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| AI fields | Premium AI field type | N/A |
| AI automation | Built-in AI agent node | N/A (could use n8n AI nodes) |
| AI in apps | Builder AI workflow action | N/A |
| Providers | OpenAI, Anthropic, Mistral, Ollama, OpenRouter | N/A |
| AI prompts | Field-aware prompt templates | N/A |

Baserow's AI integration is native and tightly coupled to the data model, allowing AI to reference row data directly. OpenRegister could achieve similar through n8n AI nodes but lacks the native field-level integration.
