---
status: draft
source: competitive-analysis
competitor: objects-api
analyzed_date: 2026-03-12
---

# JSON Merge Patch Updates — Objects API

## Purpose
PATCH operations use a modified RFC 7396 (JSON Merge Patch) to recursively merge new data into existing record data. Key deviation: null values are KEPT (not used for deletion as in the RFC).

- **Product**: Objects API
- **Category**: Data Operations
- **Relevance to OpenRegister**: OpenRegister does not have partial update with merge semantics

## Implementation

```python
def merge_patch(target, patch):
    if not isinstance(patch, dict):
        return patch
    if not isinstance(target, dict):
        target = {}
    for k, v in patch.items():
        # RFC says delete if v is None — we deviate and keep it
        target[k] = merge_patch(target.get(k), v)
    return target
```

**Test cases from the codebase**:
- `{"a": "b"} + {"a": "c"} = {"a": "c"}` (overwrite)
- `{"a": "b"} + {"b": "c"} = {"a": "b", "b": "c"}` (add)
- `{"a": "b"} + {"a": None} = {"a": None}` (null KEPT, not deleted)
- `{"a": {"b": "c"}} + {"a": {"b": "d", "c": None}} = {"a": {"b": "d", "c": None}}` (deep merge)
- `{"a": ["b"]} + {"a": "c"} = {"a": "c"}` (array replaced by scalar)

**Integration**: After merge, the resulting data is validated against the JSON Schema BEFORE saving.

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Partial update | JSON Merge Patch (modified RFC 7396) | Full object replacement or field-level |
| Null handling | Null preserved (deviation from RFC) | N/A |
| Deep merge | Recursive for nested objects | N/A |
| Validation | After merge, before save | On save |

**Already in OpenRegister**: Partial updates via PATCH
**Not yet in OpenRegister**: RFC 7396-based recursive merge patch, null-preserving semantics, merge-then-validate pattern
