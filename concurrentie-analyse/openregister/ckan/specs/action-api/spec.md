---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Action API

## What It Does

CKAN's Action API is a function-based API pattern where every operation is a named action callable via `POST /api/3/action/{action_name}`. This differs from REST resource-oriented APIs -- instead of `GET /datasets/123`, CKAN uses `POST /api/3/action/package_show` with `{"id": "123"}`.

## How It Works

The `logic/__init__.py` module (1003 lines) provides the action dispatch system. Core functions:

- `get_action(action_name)` - Looks up and returns the action function, supporting plugin overrides via `IActions` interface
- `check_access(action_name, context, data_dict)` - Runs the corresponding auth function
- `chained_action` decorator - Allows plugins to wrap existing actions while calling the original

Actions are organized by verb in separate modules:
- `action/get.py` (3198 lines, 60+ actions) - All read operations
- `action/create.py` (1477 lines) - All create operations
- `action/update.py` (1355 lines) - Full-replace update operations
- `action/delete.py` (826 lines) - Soft-delete operations
- `action/patch.py` (180 lines) - Partial update operations

Every action follows the same pattern:
1. Check authorization via `_check_access()`
2. Validate input via `_validate()` with Navl schema
3. Execute database operations via SQLAlchemy models
4. Return dictized result via `model_dictize`

Response format is always `{"success": true/false, "result": ..., "help": "..."}`.

## Key Source Files
- `ckan/logic/__init__.py` - Action dispatch, `get_action()`, `check_access()`
- `ckan/logic/action/get.py` - 60+ read actions
- `ckan/logic/action/create.py` - Create actions
- `ckan/logic/action/patch.py` - Partial update actions

## Relevance to OpenRegister

OpenRegister uses standard REST endpoints (`GET/POST/PATCH/DELETE /api/objects/{register}/{schema}`). CKAN's action pattern has the advantage of easy plugin extensibility (any action can be overridden or chained) but the disadvantage of non-standard URLs that don't follow REST conventions. OpenRegister's approach is more discoverable and standards-compliant.
