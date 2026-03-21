# n8n Workflows — Tender & Ecosystem Intelligence

All workflows are stored as JSON in this directory. They survive n8n resets.

## Import into n8n

```bash
# Start standalone n8n
docker compose -f openregister/docker-compose.yml --profile standalone up -d

# Then import via n8n UI at http://localhost:5678
# Or via API:
# curl -X POST http://localhost:5679/api/v1/workflows \
#   -H "X-N8N-API-KEY: $API_KEY" \
#   -H "Content-Type: application/json" \
#   -d @workflow.json
```

## Workflows

| File | Type | Schedule | Purpose |
|------|------|----------|---------|
| `qwen-call.json` | Sub-workflow | On-call | Reusable Qwen 3.5 API wrapper |
| `tender-classify.json` | Sub-workflow | On-call | Classify tender by CPV-based software category |
| `tender-scrape-tenderned.json` | Scheduled | Daily 06:00 | Scrape TenderNed API (planned) |
| `tender-scrape-ted.json` | Scheduled | Daily 07:00 | Scrape TED API (planned) |
| `tender-extract-requirements.json` | Sub-workflow | On-call | Extract eisen/wensen via Qwen (planned) |
| `gap-detect.json` | Scheduled | Weekly Sun | Detect ecosystem gaps (planned) |

## Qwen Configuration

- **API:** `http://host.docker.internal:11434/v1` (Ollama OpenAI-compatible)
- **Model:** `qwen3.5-optimized` (9.7B Q4_K_M, 16K context, 100% GPU)
- **Speed:** ~43 t/s without thinking, ~22 t/s with thinking
- **Default:** `think: false` for classification/extraction tasks

## Database

SQLite database at `concurrentie-analyse/intelligence.db`. Workflows read/write via `sqlite3` Execute Command nodes.
