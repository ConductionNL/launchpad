# Directus - Business Logic Flows

## Request Processing Pipeline

```mermaid
flowchart TD
    A[HTTP Request] --> B{Auth Middleware}
    B -->|Token/Session| C[Accountability Resolution]
    C --> D[Rate Limiter Check]
    D --> E{Route Type}

    E -->|REST| F[Controller]
    E -->|GraphQL| G[GraphQL Service]
    E -->|WebSocket| H[WS Controller]

    F --> I[ItemsService]
    G --> I
    H --> I

    I --> J[Validate Access]
    J --> K[Process Payload]
    K --> L[PayloadService Transform]
    L --> M{Action}

    M -->|Create| N[Insert via Knex]
    M -->|Read| O[Build AST from Query]
    M -->|Update| P[Update via Knex]
    M -->|Delete| Q[Delete via Knex]

    O --> R[Process AST Permissions]
    R --> S[Run AST via Knex]

    N --> T[Emit Action Event]
    P --> T
    Q --> T
    S --> T

    T --> U[Activity/Revision Logging]
    T --> V[Flow Trigger Check]
    T --> W[Cache Invalidation]
    T --> X[WebSocket Broadcast]
```

## Permission Resolution Flow

```mermaid
flowchart TD
    A[Request with Accountability] --> B[Fetch User Roles Tree]
    B --> C[Fetch Policies for Roles]
    C --> D[Fetch Permissions for Policies]
    D --> E{Check Global Access}

    E -->|Admin| F[Full Access - Skip Checks]
    E -->|App User| G[Apply Permission Rules]
    E -->|Public| H[Apply Public Policy Rules]

    G --> I{Action Type}
    I -->|Read| J[Process AST - Add Filter Conditions]
    I -->|Create| K[Validate Payload Against Rules]
    I -->|Update| L[Validate Item Access + Payload]
    I -->|Delete| M[Validate Item Access]
    I -->|Share| N[Validate Share Permission]

    J --> O[Field-level Filtering]
    O --> P[Apply Presets/Defaults]
    P --> Q[Execute Query with Restrictions]
```

## Flows Automation Engine

```mermaid
flowchart TD
    A[Flow Trigger] --> B{Trigger Type}

    B -->|Event| C[Item CRUD Action/Filter]
    B -->|Schedule| D[Cron Job]
    B -->|Webhook| E[HTTP Request to /flows/trigger/:id]
    B -->|Manual| F[User clicks Run in UI]
    B -->|Operation| G[Called by another Flow]

    C --> H[FlowManager]
    D --> H
    E --> H
    F --> H
    G --> H

    H --> I[Resolve Flow Tree]
    I --> J[Execute First Operation]

    J --> K{Operation Result}
    K -->|Success| L[Follow Resolve Path]
    K -->|Failure| M[Follow Reject Path]

    L --> N{More Operations?}
    M --> N

    N -->|Yes| J
    N -->|No| O[Return Result]

    subgraph "Built-in Operations"
        P[Item CRUD]
        Q[Send Mail]
        R[Send Notification]
        S[HTTP Request]
        T[Transform Data]
        U[Condition Check]
        V[Run Script]
        W[Log Message]
        X[Sleep/Delay]
        Y[JWT Sign/Verify]
        Z[Throw Error]
        AA[Trigger Flow]
    end
```

## Extension Loading Pipeline

```mermaid
flowchart TD
    A[Server Start] --> B[ExtensionManager.initialize]
    B --> C[Scan Extensions Path]
    C --> D[Load Extension Settings from DB]
    D --> E{Extension Type}

    E -->|Hook| F[Register Event Handlers]
    E -->|Endpoint| G[Mount Express Router]
    E -->|Operation| H[Register in FlowManager]
    E -->|Interface| I[Bundle for Frontend]
    E -->|Display| I
    E -->|Layout| I
    E -->|Module| I
    E -->|Panel| I
    E -->|Theme| I

    F --> J[Emitter.onAction/onFilter/onInit]
    G --> K[app.use /custom-endpoint]
    H --> L[flowManager.addOperation]
    I --> M[Rollup/Rolldown Build]
    M --> N[Serve via /extensions/sources]

    subgraph "Sandboxed Execution"
        O[isolated-vm]
        P[SDK Proxy to Services]
    end

    F -.-> O
    G -.-> O
    H -.-> O
```

## File Upload and Asset Transformation Flow

```mermaid
flowchart TD
    A[File Upload Request] --> B[FilesService.uploadOne]
    B --> C[Resolve Storage Location]
    C --> D[Stream to Storage Driver]
    D --> E[Extract Metadata]
    E --> F[Save to directus_files]

    G[Asset Request /assets/:id] --> H[AssetsService.getAsset]
    H --> I{Transformation Requested?}

    I -->|No| J[Stream Original from Storage]
    I -->|Yes| K[Check Transform Cache]

    K -->|Cache Hit| L[Return Cached Transform]
    K -->|Cache Miss| M[Load Original]
    M --> N[Sharp Transform Pipeline]
    N --> O[width/height/fit/quality/format]
    O --> P[Save to Transform Cache]
    P --> L

    subgraph "Storage Drivers"
        Q[Local Filesystem]
        R[Amazon S3]
        S[Google Cloud Storage]
        T[Azure Blob Storage]
        U[Cloudinary]
        V[Supabase Storage]
    end
```

## WebSocket Subscription Flow

```mermaid
flowchart TD
    A[Client WS Connect] --> B[Authenticate]
    B --> C{Auth OK?}
    C -->|No| D[Close Connection]
    C -->|Yes| E[Create WebSocketClient]

    E --> F[Client Sends Subscribe Message]
    F --> G[Parse Collection + Query]
    G --> H[Validate Read Access]
    H --> I[Register Subscription]

    J[Item Mutation Event] --> K[Emitter Action]
    K --> L[Check Subscribed Clients]
    L --> M{Client Has Access?}
    M -->|Yes| N[Push Update to Client]
    M -->|No| O[Skip]

    subgraph "GraphQL WS"
        P[graphql-ws Protocol]
        Q[Schema-based Subscriptions]
    end

    subgraph "REST WS"
        R[Custom Message Protocol]
        S[CRUD + Subscribe/Unsubscribe]
    end
```

## Content Versioning Flow

```mermaid
flowchart TD
    A[Create Version] --> B[VersionsService.createOne]
    B --> C[Validate Collection Has Versioning Enabled]
    C --> D[Validate User Has Read Access to Item]
    D --> E[Store Version Record in directus_versions]

    F[Save to Version] --> G[VersionsService.save]
    G --> H[Validate User Permissions]
    H --> I[Compute Delta from Main]
    I --> J[Store Delta in Version]

    K[Promote Version] --> L[VersionsService.promote]
    L --> M[Merge Version Delta into Main Item]
    M --> N[Update Main Item via ItemsService]
    N --> O[Create Revision Record]
    O --> P[Optionally Delete Version]

    Q[Compare Versions] --> R[Read Main Item]
    R --> S[Apply Version Delta]
    S --> T[Return Merged Result]
```
