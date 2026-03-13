# Authentication & Authorization Flow

```mermaid
flowchart TD
    A[API Request] --> B{Authorization header present?}
    B -->|No| C[401 Unauthorized]
    B -->|Yes| D[Extract token from 'Token xxx' header]
    D --> E{TokenAuth.objects.get token=xxx}
    E -->|DoesNotExist| F[401 Invalid token]
    E -->|Found| G[Set request.auth = TokenAuth instance]
    G --> H[Set request.user = None]
    H --> I{TokenPermissions.has_permission}
    I --> J{request.auth is not None?}
    J -->|No| K[403 Forbidden]
    J -->|Yes| L[FULL ACCESS - no RBAC]
    L --> M[Execute view]
    M --> N[Log operation with token.identifier + token.application]
```

## Key Design Decisions

1. **No user association**: Tokens are NOT linked to Django User objects. `request.user` is always `None`.
2. **No RBAC whatsoever**: Any valid token has full read/write/delete access to ALL endpoints.
3. **Audit via token metadata**: Each token has `identifier`, `application`, `organization` fields that are logged with every operation for traceability.
4. **Setup configuration**: Tokens can be provisioned automatically via YAML config during deployment (TokenAuthConfigurationStep).
