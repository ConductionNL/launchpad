---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# MCP Support

## Summary

Baserow has early MCP (Model Context Protocol) support for exposing database tables and rows as MCP tools. The implementation is minimal (~400 lines) and provides basic table listing and row operations.

## Architecture

Located at `backend/src/baserow/contrib/database/mcp/`

```
mcp/
  __init__.py
  rows/
    tools.py          # Row MCP tools (277 lines)
  table/
    tools.py          # Table MCP tools (42 lines)
    utils.py          # Table utilities (87 lines)
```

## MCP Tools

### Table Tools
- List tables in a database
- Basic table metadata

### Row Tools
- CRUD operations on rows
- Filtering and querying
- Field-aware operations

## Implementation Scale

- Total: ~406 lines of Python
- Very early stage implementation
- Not yet a comprehensive MCP server

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| MCP protocol | Early implementation (~400 LOC) | Full MCP server (JSON-RPC 2.0) |
| Transport | Unknown (likely HTTP) | Streamable HTTP |
| Resources | Basic tables + rows | Registers, schemas, objects |
| Tools | Table list, row CRUD | Full CRUD for all entities |
| Maturity | Alpha/early | Production-ready |

OpenRegister's MCP implementation is significantly more mature and comprehensive than Baserow's early effort.
