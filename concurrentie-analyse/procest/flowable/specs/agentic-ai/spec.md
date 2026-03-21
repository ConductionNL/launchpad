---
competitor: flowable
analyzed_date: 2026-03-14
feature: Agentic AI
category: platform
---

# Agentic AI

## What It Is

Flowable's Agentic AI capability (released 2025+, branded as "Agentic Case Platform") integrates AI agents directly into the CMMN case management engine. This is Flowable's newest major feature, representing their move from process automation to intelligent automation.

## Key Capabilities

### Agent Types

1. **Orchestrator AI Agents**
   - Embedded directly in the CMMN engine
   - Govern case progression and routing
   - Make decisions within case context
   - Full audit trail for all agent actions

2. **Knowledge AI Agents**
   - RAG (Retrieval Augmented Generation) based
   - Access organizational knowledge bases
   - Answer questions using enterprise data
   - Context-aware responses within case scope

3. **Document AI Agents**
   - Document classification (categorizing incoming documents)
   - Data extraction from documents
   - Intelligent document processing (IDP)
   - Integrated with case context

4. **Utility AI Agents**
   - Tool-based agents
   - Execute specific functions
   - API integrations
   - System interactions

5. **A2A (Agent-to-Agent) Agents**
   - Inter-agent communication
   - Multi-agent orchestration
   - Distributed agent workflows

6. **External Agents**
   - Azure AI Foundry integration
   - AWS Bedrock integration
   - Salesforce Agentforce integration
   - Extensible connector framework

### LLM Support
- OpenAI
- Azure OpenAI
- Anthropic (Claude)
- Bring Your Own LLM (BYOL)

### AI-Assisted Modeling
- AI-assisted BPMN/CMMN model generation
- Natural language to process model
- AI-suggested optimizations

### Governance & Guardrails
- All agent actions recorded in case audit trail
- Human oversight built into agent workflows
- Defined permissions for agent operations
- Transparent decision-making process
- Compliance-ready agent governance

## Architecture

- Agents operate within CMMN case context
- MCP (Model Context Protocol) for centralized agent orchestration
- A2A architecture for multi-agent coordination
- Every agent action tied to case instance
- Human-in-the-loop checkpoints configurable

## Availability

- Only available in **Agentic Case Platform** tier (highest commercial tier)
- Not available in Community Edition or base Flowable Platform
- Requires separate Agent Packages pricing
- Released as part of Flowable 2025.2

## Relevance to Procest

### Applicable Patterns
- AI agents within case context (not standalone)
- Document classification and extraction for incoming documents
- RAG for organizational knowledge access
- Human oversight for AI decisions
- Audit trail for all AI actions

### Key Differences
- Flowable embeds agents in CMMN engine; Procest would use n8n AI nodes
- Flowable's AI is commercial-only; Procest could offer open-source AI integration
- Flowable focuses on enterprise AI governance; Procest targets practical government AI use

### Opportunities
- Procest can differentiate with open-source AI integration via n8n + Ollama
- Document AI for government correspondence processing is high value
- Government-focused AI governance (BIO compliance, GDPR) vs generic enterprise
- Local LLM deployment (Ollama) for data sovereignty vs cloud-only LLMs
- n8n provides more flexible AI agent orchestration than Flowable's CMMN-embedded approach
