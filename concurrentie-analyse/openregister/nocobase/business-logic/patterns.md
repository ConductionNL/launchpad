---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# NocoBase Business Logic Patterns

## Core Patterns

### 1. Plugin-Everything Architecture
Every feature is a plugin, including core functionality. The application core is intentionally minimal (Koa + Sequelize + ACL), with all business features implemented as plugins. This enables:
- Hot-enable/disable of any feature
- Clear separation of concerns
- Independent versioning and updates
- Third-party extensibility

### 2. Schema-Driven UI
The entire UI is defined as JSON schemas stored in the database. This decouples the interface from code and enables:
- Runtime UI customization without deployment
- Non-developer interface building
- UI versioning and rollback
- Template/reuse patterns

### 3. Collection-First Data Model
Data modeling starts with Collections (database tables), not with abstract schemas. This provides:
- Direct SQL access for complex queries
- PostgreSQL-specific features (inheritance, FDW)
- Familiar relational model
- Automatic API generation per collection

### 4. Resource-Action API
Instead of REST verbs, NocoBase uses a `resource:action` pattern that maps more closely to business operations:
- Standard CRUD via `list`, `get`, `create`, `update`, `destroy`
- Association management via `add`, `remove`, `set`, `toggle`
- Custom actions for business-specific operations
- Automatic API docs via swagger plugin

### 5. Registry Pattern
Many subsystems use a Registry pattern for extensibility:
- `triggers: Registry<Trigger>` - Workflow triggers
- `instructions: Registry<Instruction>` - Workflow nodes
- `storageTypes: Registry<StorageType>` - File storage backends
- `availableStrategy: Map<string, ACLAvailableStrategy>` - ACL strategies

### 6. Event-Driven Hooks
Sequelize model hooks are used extensively:
- Audit logging on create/update/destroy
- Workflow triggers on collection changes
- ACL cache invalidation on role changes
- Cache clearing on data updates

## Architecture Decisions

### Database as Source of Truth
- Collections are database tables, not abstractions
- UI schemas stored in database tables
- Plugin state in database
- No external configuration files for runtime state

### Monolithic Server
- Single Node.js process serves API + static files
- PM2 for process management
- Nginx reverse proxy for static files
- No microservices architecture

### Sequelize ORM
- Full ORM with migrations, associations, scopes
- Query building with operators
- Transaction support
- Multi-dialect support (PostgreSQL, MySQL, SQLite)

### Formily for Forms
- Schema-based form rendering
- Validation rules in schema
- Dynamic form behavior (show/hide, calculated fields)
- Support for complex nested forms

## Key Differentiators from OpenRegister

1. **No-code focus** - NocoBase prioritizes non-developer users; OpenRegister is developer-oriented
2. **Tight coupling** - UI, data, and logic are tightly integrated; OpenRegister separates concerns
3. **Standalone** - NocoBase is a complete application; OpenRegister is an ecosystem component
4. **Commercial model** - AGPL + commercial license; OpenRegister is EUPL open source
5. **Chinese market** - Strong Chinese government/enterprise focus; OpenRegister focuses on Dutch/EU market
