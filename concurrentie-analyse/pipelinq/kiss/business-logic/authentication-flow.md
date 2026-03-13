# Authentication Flow - KISS

OIDC login + role-based access control

```mermaid
flowchart TD
    A[User navigates to KISS URL] --> B[Vue frontend loads]
    B --> C{Valid session cookie?}

    C -->|Yes| D[Load user profile from session]
    C -->|No| E[Redirect to BFF /login]

    E --> F[BFF initiates OIDC Authorization Code Flow + PKCE]
    F --> G[Redirect to Identity Provider]
    G --> H[User authenticates: username + password + MFA]
    H --> I{Auth successful?}
    I -->|No| J[Show error / retry]
    I -->|Yes| K[IdP redirects back with authorization code]

    K --> L[BFF exchanges code for tokens]
    L --> M[BFF receives: access_token + id_token + refresh_token]
    M --> N[BFF creates server-side session]
    N --> O[Store tokens in session, NOT exposed to frontend]
    O --> P[Set encrypted session cookie]
    P --> Q[Redirect to KISS frontend]

    Q --> R[Frontend loads with valid session]
    R --> S[Extract user info from session]
    S --> T[Map OIDC claims to permissions]

    T --> U{Role determination}
    U --> V[Check role claims from IdP]

    V --> W{Has Beheerder claim?}
    W -->|Yes| X[Grant: KCM + Redacteur + Beheerder permissions]
    W -->|No| Y{Has Redacteur claim?}
    Y -->|Yes| Z[Grant: KCM + Redacteur permissions]
    Y -->|No| AA[Grant: KCM permissions only]

    X --> AB[Render UI with admin menu visible]
    Z --> AB[Render UI with content menu visible]
    AA --> AC[Render UI with KCM features only]

    AB --> AD[User interacts with KISS]
    AC --> AD

    AD --> AE{API request to external service}
    AE --> AF{Authorization mode?}

    AF -->|Application-level| AG[BFF uses shared API token]
    AF -->|User-level: e-Suite| AH[BFF creates JWT with user identifier]
    AG --> AI[Forward request to external API]
    AH --> AI

    style E fill:#e3f2fd
    style H fill:#fff3e0
    style N fill:#c8e6c9
    style T fill:#f3e5f5
```

## Token Refresh Flow

```mermaid
flowchart TD
    A[Frontend makes API request] --> B[BFF checks access_token validity]
    B --> C{Token expired?}
    C -->|No| D[Forward request with valid token]
    C -->|Yes| E{Refresh token available?}
    E -->|Yes| F[Exchange refresh_token for new tokens]
    F --> G{Refresh successful?}
    G -->|Yes| H[Update tokens in session]
    H --> D
    G -->|No| I[Clear session]
    E -->|No| I
    I --> J[Return 401 to frontend]
    J --> K[Frontend redirects to /login]
```

## YARP Proxy Authorization

```mermaid
flowchart LR
    A[Frontend request] --> B[YARP Reverse Proxy]
    B --> C{Route policy?}
    C -->|No auth required| D[Forward to external API]
    C -->|RedactiePolicy| E{User has Redacteur role?}
    C -->|BeheerPolicy| F{User has Beheerder role?}
    E -->|Yes| D
    E -->|No| G[403 Forbidden]
    F -->|Yes| D
    F -->|No| G
```

## Role Permissions Matrix

| Feature | KCM | Redacteur | Beheerder |
|---------|-----|-----------|-----------|
| Create contactmomenten | Yes | Yes | Yes |
| Search BRP/KvK/cases | Yes | Yes | Yes |
| Search knowledge base | Yes | Yes | Yes |
| Create contactverzoeken | Yes | Yes | Yes |
| Manage werkberichten | No | Yes | Yes |
| Manage VACs | No | Yes | Yes |
| Manage kennisartikelen | No | Yes | Yes |
| Manage kanalen | No | No | Yes |
| Manage skills | No | No | Yes |
| Manage links | No | No | Yes |
| Manage gespreksresultaten | No | No | Yes |
| Manage contactverzoek forms | No | No | Yes |
| Access management info API | No | No | Yes |

## Employee Identity Mapping

The `OIDC_MEDEWERKER_IDENTIFICATIE_CLAIM` maps the authenticated user to their employee record in the Objects API (smoelenboek). This is used for:
1. Attributing contactmomenten to the handling KCM
2. Attributing verwerking (audit) log entries
3. Auto-assigning contactverzoeken when an employee creates one for their own department
4. Displaying "logged in as" information in the UI
