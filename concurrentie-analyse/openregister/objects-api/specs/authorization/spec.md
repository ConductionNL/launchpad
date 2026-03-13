---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# Authorization — Objects API (Documentation View)

## Purpose
Fine-grained, per-objecttype authorization using API tokens. Supports read-only, read-and-write, field-level access control, and superuser tokens.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/admin/authorization.html

## Authorization Model

### Objecttypes API
No granular authorization — every authenticated client has access to all object types.

### Objects API
Per-objecttype permissions configured via admin:

1. **Service configuration** — Register the Objecttypes API as a service with its token
2. **Object type registration** — Register specific objecttypes by service + UUID
3. **Permission assignment** — Assign permissions per token per objecttype

### Permission Modes
| Mode | Description |
|------|-------------|
| `read_only` | Can only read objects of this type |
| `read_and_write` | Can read and write objects of this type |

### Field-Based Authorization
- Available for `read_only` permission mode only
- Select specific fields the token can access
- Hidden fields reported in `X-Unauthorized-Fields` response header
- Format: `objectType1:fieldA,fieldB; objectType2:fieldC,fieldD`

### Superuser Tokens
- `is_superuser` flag on token
- Full read/write access to all objecttypes
- Recommended only for test/development

### Permissions API
| Method | Path | Description |
|--------|------|-------------|
| GET | `/permissions` | List permissions for current token |

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Auth method | API tokens (40-char) | Nextcloud user/app auth |
| Per-type permissions | Yes (read_only / read_and_write) | Via Nextcloud groups |
| Field-level access | Yes (read-only mode) | No |
| Superuser tokens | Yes | Admin users |
| Permission inspection API | GET /permissions | No equivalent |
| Token management | Admin UI | Nextcloud settings |
| Hidden fields header | X-Unauthorized-Fields | No |

**Already in OpenRegister**: Authentication (Nextcloud-based), basic authorization
**Not yet in OpenRegister**: Per-objecttype token permissions, field-level authorization, permission inspection API, X-Unauthorized-Fields header
