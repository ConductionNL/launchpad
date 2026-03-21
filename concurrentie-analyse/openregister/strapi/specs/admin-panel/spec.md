---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Admin Panel

## Overview

Strapi's admin panel is a React-based single-page application that provides a visual interface for content management, schema editing, user management, and system configuration. It is served by the Strapi backend and communicates with the admin API routes. The panel features a custom design system, plugin injection zones, and extensive customization capabilities.

## Tech Stack

| Technology | Usage |
|-----------|-------|
| React 18 | UI framework |
| React Router | Client-side routing |
| Redux Toolkit | State management |
| React Query | Server state + caching |
| Custom Design System | UI component library |
| react-intl | Admin panel translations |
| Formik / Custom forms | Form management |
| Vite (via strapi build) | Build tooling |

## Architecture

```
admin/
  admin/src/
    App.tsx               # Root application
    StrapiApp.tsx          # Plugin-aware app shell
    components/           # Shared UI components
    core/                 # Core utilities
    features/             # Feature-specific modules
    hooks/                # Custom React hooks
    layouts/              # Page layouts
    pages/                # Page components
      Auth/               # Login, registration
      Home/               # Dashboard
      Settings/           # System settings
      ProfilePage.tsx     # User profile
    services/             # API service layer
    translations/         # i18n strings
    router.tsx            # Route definitions
    reducer.ts            # Redux store
  server/src/             # Admin API backend
    controllers/
    routes/
    services/
    strategies/           # Auth strategies (session, API token)
```

## Key Pages

### Content Manager
- List view with configurable columns, filters, and sorting
- Detail view with field editors matching content type schema
- Inline relation management (create/edit related entries)
- Draft/publish toggle
- Locale selector (with i18n plugin)
- History versions panel
- Review workflow stage selector (EE)

### Content-Type Builder
- Visual schema editor for content types and components
- Drag-and-drop field ordering
- Field type selector with configuration modals
- Relation editor with visual diagram
- Component category management

### Media Library
- Grid/list view for uploaded files
- Folder navigation
- Drag-and-drop upload
- Image preview with metadata editing
- Bulk operations

### Settings
- General settings (app name, default locale)
- Users & roles management (admin users)
- API tokens management
- Webhooks configuration
- Email settings
- Transfer tokens (for data transfer between environments)
- Plugin-specific settings

## Design System

Strapi has its own design system with components:
- Typography (Heading, Body, etc.)
- Form inputs (TextInput, NumberInput, Select, etc.)
- Layout (Box, Flex, Grid, Dialog, Modal)
- Data display (Table, Badge, Status, Tag)
- Navigation (Tabs, Breadcrumbs, Pagination)
- Feedback (Alert, Toast, Loader)

Components follow WCAG accessibility guidelines.

## Customization

### Admin Panel Extension
Users can customize the admin in `src/admin/`:
```typescript
// src/admin/app.tsx
export default {
  config: {
    locales: ['fr', 'nl'],
    tutorials: false,
    notifications: { releases: false },
    theme: {
      light: { colors: { primary500: '#1a73e8' } },
    },
    head: {
      favicon: '/favicon.ico',
    },
  },
  bootstrap(app) {
    // Add menu items, injection zone components
  },
};
```

### Injection Zones
Predefined slots where plugins can inject UI:
- Content Manager edit view (sidebar, top)
- Content Manager list view (actions)
- Admin settings page

### Theme Customization
- Light and dark mode support
- Customizable color palette
- Logo replacement
- Custom favicon

## Internationalization

The admin panel itself supports multiple languages:
- 20+ admin interface languages
- Plugin-contributed translations
- Dynamic locale loading

## Relevance to OpenRegister

**Key differences:**
- Strapi has a purpose-built admin panel; OpenRegister uses Nextcloud's admin + Vue frontend apps
- Strapi's admin is tightly coupled to its backend; OpenRegister UIs are separate Nextcloud apps
- Strapi's design system is custom; OpenRegister uses Nextcloud Vue components + NL Design

**Observations:**
- The Content-Type Builder visual editor is a major UX differentiator for Strapi
- Injection zones pattern is useful for plugin extensibility
- The admin panel accounts for ~40% of Strapi's codebase (significant investment)
- OpenRegister benefits from Nextcloud's existing admin infrastructure and avoids this overhead
