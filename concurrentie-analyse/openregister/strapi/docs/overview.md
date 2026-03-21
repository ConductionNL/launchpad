# Strapi - Comprehensive Overview

Source: https://docs.strapi.io/cms/intro

## What is Strapi?

Strapi is an open-source, JavaScript-based **headless CMS** that provides a customizable admin panel and auto-generated REST and GraphQL APIs. It is built on Node.js with a React-based admin panel, and supports SQLite, PostgreSQL, MySQL/MariaDB databases. Strapi 5 is the current major version, requiring Node.js 20+.

Strapi positions itself as a "Content Operating System" that lets developers build content-rich applications while giving content editors a user-friendly interface. It is self-hosted (with an optional Strapi Cloud offering) and extensible through a plugin ecosystem.

## Architecture

- **Backend**: Node.js (Koa.js framework), TypeScript supported
- **Admin Panel**: React single-page application (Vite or Webpack bundler)
- **Database**: SQLite (default/dev), PostgreSQL, MySQL/MariaDB (production)
- **API Layer**: Auto-generated REST API + optional GraphQL plugin
- **Plugin System**: First-class plugin architecture for extending both backend and admin panel

## Core Concepts

### Content Types
Strapi organizes content into three main structures:

1. **Collection Types**: Repeatable content structures (like database tables) - e.g., Articles, Products, Users
2. **Single Types**: One-off content structures - e.g., Homepage, Global Settings, About Page
3. **Components**: Reusable field groups that can be shared across content types
4. **Dynamic Zones**: Flexible areas where editors can pick from multiple components

### Field Types
Content types support these field types:
- **Text**: Short text, Long text, Rich text (Markdown/Blocks)
- **Number**: Integer, Big integer, Float, Decimal
- **Date**: Date, Time, Datetime
- **Boolean**
- **Email**
- **Password** (hashed)
- **Enumeration** (predefined values)
- **JSON** (arbitrary JSON data)
- **Media** (images, video, files via Media Library)
- **Relation** (one-to-one, one-to-many, many-to-many, polymorphic)
- **UID** (auto-generated unique identifier, e.g., slugs)
- **Custom Fields** (extensible via plugins)

### Documents
In Strapi 5, content entries are called "documents." A document can have:
- Multiple **draft/published** versions (Draft & Publish feature)
- Multiple **locale** versions (Internationalization feature)
- A **history** of changes (Content History feature)

## Key Features

### Admin Panel
- React-based GUI for content management
- Content Manager: CRUD interface for all content types
- Content-Type Builder: Visual UI to define schemas (no code required)
- Customizable homepage with widgets
- Theming support (light/dark modes, custom themes)
- Customizable logos, favicon, and locales

### REST API (Auto-generated)
- Full CRUD endpoints for every content type: `GET /api/:pluralApiId`, `GET /api/:pluralApiId/:documentId`, `POST`, `PUT`, `DELETE`
- Advanced query parameters:
  - **Filtering**: `filters[field][$eq]=value`, supports `$ne`, `$lt`, `$gt`, `$in`, `$contains`, `$null`, deep filtering on relations
  - **Sorting**: `sort[0]=field:asc`
  - **Pagination**: `pagination[page]=1&pagination[pageSize]=25` or offset-based `pagination[start]=0&pagination[limit]=10`
  - **Population**: `populate=*` (all relations), `populate[relation]=*` (specific), nested population
  - **Field Selection**: `fields[0]=title&fields[1]=description`
  - **Publication State**: `status=draft` or `status=published`
  - **Locale**: `locale=en` or `locale=fr`

### GraphQL API (Plugin)
- Auto-generated schema from content types
- Queries and mutations for all CRUD operations
- Filtering, sorting, pagination via GraphQL arguments
- Custom resolvers and schema extensions
- Shadow CRUD (automatic type generation)

### Users & Permissions
- Two user systems:
  1. **Admin users**: Backend/admin panel access with RBAC roles
  2. **End users**: Frontend/API users managed by Users & Permissions plugin
- JWT-based authentication
- OAuth providers (GitHub, Google, Facebook, Twitter, Discord, etc.)
- Granular permissions per content type and action
- Email confirmation and password reset flows
- API Tokens for machine-to-machine access (read-only, full-access, or custom)

### Role-Based Access Control (RBAC)
- Admin roles: Super Admin, Editor, Author (default)
- Custom roles with granular permissions per content type, field, and action
- Conditions on permissions (e.g., "own entries only")
- Enterprise features: SSO, Audit Logs

### Media Library
- Upload and manage files (images, videos, documents)
- Image optimization and responsive formats
- Folder organization
- Provider support: Local filesystem, AWS S3, Cloudinary, custom providers
- API endpoints for upload, retrieval, and deletion

### Internationalization (i18n)
- Multi-locale content management
- Per-content-type locale enablement
- Default locale configuration
- API filtering by locale
- Locale-specific CRUD operations

### Draft & Publish
- Content can be in draft or published state
- Publish/unpublish individual entries
- API filtering by publication status
- Automatic draft creation

### Content History
- Track changes to content over time
- Restore previous versions
- Compare versions (Enterprise)

### Webhooks
- Event-driven notifications to external services
- Events: entry.create, entry.update, entry.delete, entry.publish, entry.unpublish, media.create, media.update, media.delete
- Configurable URL, headers, and events
- Lifecycle hooks for custom server-side logic

### Releases
- Group content changes into releases
- Schedule publication of grouped content
- Review all changes before publishing

### Review Workflows (Enterprise)
- Multi-stage content review process
- Custom workflow stages
- Assign reviewers
- Track content through approval pipeline

### Single Sign-On (SSO) (Enterprise)
- SAML, OAuth2, OpenID Connect
- Auto-registration of SSO users
- Role mapping from SSO providers

### Audit Logs (Enterprise)
- Track admin panel actions
- User activity history
- Filterable by user, action, date

## Plugin System

### Built-in Plugins
- **Users & Permissions**: Authentication, roles, providers
- **Upload (Media Library)**: File management
- **i18n**: Internationalization
- **Content Releases**: Grouped publishing
- **Review Workflows**: Content approval (Enterprise)

### Plugin Architecture
- Plugins can extend both server (Node.js) and admin panel (React)
- Server-side: content types, controllers, services, routes, policies, middlewares
- Admin-side: injection zones, navigation items, settings sections, custom fields
- Plugin SDK for scaffolding and development
- Marketplace for discovering and installing community plugins

### Marketplace
- In-admin marketplace browser
- npm-based plugin installation
- Community and verified plugins
- Plugin compatibility checking

## Backend Customization

### Controllers
- Override or extend auto-generated CRUD controllers
- Custom controller actions
- Access to Strapi services and utilities

### Services
- Business logic layer
- Custom service functions
- Access to Document Service API for data operations

### Routes
- Customize API routes
- Add route middlewares and policies
- Content API vs. Admin API routes

### Policies
- Reusable permission checks
- Global and route-level policies
- Custom policy logic

### Middlewares
- Request/response processing pipeline
- Route-level and global middlewares
- Built-in middlewares: CORS, body parser, security, session, etc.

### Models / Content Types (Code-level)
- Schema definition in JSON files
- Lifecycle hooks: beforeCreate, afterCreate, beforeUpdate, afterUpdate, beforeDelete, afterDelete, etc.
- Model configuration: draftAndPublish, populateCreatorFields, tableName

## Database & Configuration

### Supported Databases
- SQLite (development default)
- PostgreSQL (recommended for production)
- MySQL / MariaDB

### Configuration Files
- `config/database.js` - Database connection
- `config/server.js` - Server host, port, CORS
- `config/admin.js` - Admin panel settings
- `config/api.js` - API settings (default pagination, etc.)
- `config/middlewares.js` - Middleware stack
- `config/plugins.js` - Plugin configuration
- `.env` - Environment variables

### Environment-based Configuration
- `config/env/{environment}/` directory structure
- Automatic environment detection
- Separate configs for development, staging, production

## Deployment

### Self-hosted
- Any Node.js hosting (VPS, dedicated server)
- Docker support
- Process managers (PM2)
- Reverse proxy (Nginx, Apache)

### Strapi Cloud
- Managed hosting by Strapi
- Automatic deployments from Git
- Built-in PostgreSQL database
- CDN for media

### Database Requirements for Production
- PostgreSQL recommended
- MySQL/MariaDB supported
- SQLite NOT recommended for production

## CLI Commands
- `strapi develop` - Start development server with auto-reload
- `strapi start` - Start production server
- `strapi build` - Build admin panel
- `strapi generate` - Generate boilerplate (content-types, plugins, etc.)
- `strapi import/export` - Data import/export
- `strapi transfer` - Transfer data between instances

## TypeScript Support
- Full TypeScript support for Strapi 5
- Auto-generated types for content types
- Type-safe Document Service API
- TypeScript plugin development

## Key Technical Details
- **Port**: Default 1337
- **Admin Panel**: `/admin` route
- **API Prefix**: `/api/` for content API
- **Authentication**: JWT tokens (Bearer header) or API Tokens
- **File Structure**: `/src/api/` for content types, `/src/plugins/` for local plugins, `/config/` for configuration
- **Content API Token Types**: read-only, full-access, custom (per content type/action)
