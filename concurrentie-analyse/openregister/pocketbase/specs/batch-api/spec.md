---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Batch API

## Summary
PocketBase supports transactional batch operations, allowing multiple CRUD actions to be executed atomically in a single HTTP request.

## Key Features
- POST `/api/batch` with array of internal requests
- Supported operations: create, update, delete, upsert (PUT)
- All operations execute in a single database transaction
- If any operation fails, the entire batch is rolled back
- Each request result returned with status code and body
- Configurable body size limit
- Regex-based route matching for supported batch actions

## Architecture
- `apis/batch.go` - Batch handler with transaction wrapping
- `core/event_request_batch.go` - Batch request event types
- Uses `ValidBatchActions` map with regex patterns for route matching

## Relevance to OpenRegister
OpenRegister does not currently have a batch API. This is valuable for bulk data imports and multi-record updates that need to be atomic.
