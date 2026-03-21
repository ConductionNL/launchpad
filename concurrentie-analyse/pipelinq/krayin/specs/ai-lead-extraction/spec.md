---
competitor: krayin
analyzed_date: 2026-03-14
feature: ai-lead-extraction
priority: low
---

# AI Lead Extraction

## Overview

Krayin includes AI-powered lead extraction from uploaded files (PDFs and images) using OpenRouter's API. Documents are parsed, sent to an LLM, and structured lead data is extracted automatically.

## Flow

1. User uploads files (PDF, JPEG, PNG, BMP, WebP)
2. PDFs parsed with smalot/pdfparser for text + images
3. Content sent to OpenRouter API with system prompt
4. LLM returns JSON: title, lead_value, person name/email/phone
5. Response validated and lead + person created

## Configuration

- Model: configurable via admin settings
- API: OpenRouter (https://openrouter.ai/api/v1/chat/completions)
- Max tokens: 100,000 chars (truncates keeping first/last 40%)
- Person deduplication by email on import

## Pipelinq Comparison Notes

- Innovative but basic -- only extracts 5 fields
- Uses third-party OpenRouter, not local AI
- Pipelinq could use Nextcloud AI assistant for similar features
- Token truncation may lose important content
