# Row-Level Security (Fine-Grained Access Control)

## Feature Summary
Directus implements comprehensive row-level security through its permission system, allowing administrators to restrict which items (rows) a user can access within a collection based on filter rules and dynamic variables.

## How Directus Implements This

### Permission Hierarchy
```
Users -> Roles -> Policies -> Permissions
         (organizational)  (additive groups)  (collection+action)
```

### Row-Level Filtering
Permissions use filter rules to restrict item access:
```json
{
    "user_created": { "_eq": "$CURRENT_USER" }
}
```

This restricts users to only see items they created.

### Field-Level Permissions
Different fields accessible per action:
- Read: `["title", "content", "status"]`
- Update: `["content"]` (can read but not update title/status)

### Dynamic Variables
- `$CURRENT_USER` — Current user's primary key (+ nested fields)
- `$CURRENT_ROLE` — Current role's primary key (+ nested fields)
- `$NOW` — Current timestamp (with offset support)

### Policy System (Directus 11)
- Policies are groups of permissions
- Multiple policies per user/role
- **Additive** — policies can only expand access, never restrict
- IP-based policy filtering (subtractive — policies excluded if IP doesn't match)

### Combining Policies
- **Field permissions:** Union of all policy field grants
- **Item rules:** Combined with logical OR
- **IP access:** Subtractive — non-matching policies excluded

### Field Validation
```json
{
    "title": { "_regex": "^[A-Z]" },
    "content": { "_nnull": true }
}
```

### Field Presets
Default values applied on create/update:
```json
{
    "status": "draft",
    "user_created": "$CURRENT_USER"
}
```

## OpenRegister Current State
OpenRegister relies on Nextcloud's group-based permission system. There is no built-in row-level security, field-level permissions, or dynamic variable-based access control. Permissions are at the register/schema level, not at the individual item or field level.

## Gap Analysis

| Capability | Directus | OpenRegister |
|-----------|----------|-------------|
| Row-Level Security | Filter rules per permission | None |
| Field-Level Permissions | Per action (CRUD) | None |
| Dynamic Variables | $CURRENT_USER, $CURRENT_ROLE, $NOW | None |
| Additive Policy System | Yes (v11) | None |
| IP-Based Restrictions | Per policy | Via reverse proxy |
| Field Validation in Permissions | Yes | JSON Schema validation only |
| Field Presets | Yes | Default values in schema |
| Share Permissions | Dedicated action | None |
| Public Role | Configurable | Public API endpoints |

## Competitive Impact
**High** — Row-level security is critical for multi-tenant applications, government data management, and compliance scenarios. The lack of fine-grained access control is a significant gap for organizations with complex data access requirements.

## Recommendation
Priority enhancement for OpenRegister:
1. Implement filter-based item permissions (row-level security) using the existing filter infrastructure
2. Add field-level permissions per CRUD action
3. Support dynamic variables ($CURRENT_USER equivalent) in permission filters
4. Consider an additive policy system similar to Directus 11
5. Integrate with Nextcloud's group system for role assignment
