# Twenty CRM - Platform & Extensibility

**Analyzed:** 2026-03-14

## App Platform (Alpha)

Twenty has a full app development platform allowing developers to extend the CRM with custom functionality.

### SDK Capabilities

Apps can define:
- **Custom objects** -- New data entities
- **Custom fields** -- Additional fields on existing objects
- **Logic functions** -- Business logic with triggers (pre/post-install, record events)
- **Front components** -- React UI components
- **Views** -- Saved view configurations
- **Navigation menu items** -- Sidebar links
- **Roles** -- Permission definitions
- **AI Skills** -- Agent skill definitions

### Development Workflow

```bash
npx create-twenty-app@latest my-twenty-app
cd my-twenty-app
yarn twenty app:dev    # Live sync to workspace
```

**Prerequisites:** Node.js 24+, Yarn 4, Twenty workspace with API key

### Project Structure

```
src/
  application-config.ts  (required)
  roles/                 Role definitions
  objects/               Custom object definitions
  fields/                Field definitions
  logic-functions/       Business logic
  front-components/      React UI components
  views/                 Saved views
  navigation-menu-items/ Sidebar links
  skills/                AI agent skills
public/                  Static assets
```

### Entity Detection
Uses AST parsing -- detects entities via `export default define<Entity>({...})` pattern. File organization is flexible.

### Helper Functions
`defineObject`, `defineField`, `defineLogicFunction`, `definePreInstallLogicFunction`, `definePostInstallLogicFunction`, `defineFrontComponent`, `defineRole`, `defineView`, `defineNavigationMenuItem`, `defineSkill`

## Distribution

| Channel | Scope | Method |
|---------|-------|--------|
| **npm Marketplace** | Public | Publish with `twenty-app-` prefix; auto-discovered |
| **Internal Tarball** | Server-scoped | `npx twenty app:publish --server <url>` |
| **Development** | Local | `yarn twenty app:dev` |

No review process or monetization system for marketplace apps. CI/CD via included GitHub Actions workflows.

## Security & Access Control

### Permissions System
- **Role-based access control (RBAC)**
- **Three-level cascade:** All Objects > Object-level > Field-level
- Object permissions: See, Edit, Delete, Destroy records
- Field permissions: See, Edit, No Access
- Settings permissions: API keys, workspace, roles, data model, security, workflows
- Action permissions: Send Email, Import/Export CSV

### SSO Support (Organization plan)
- **SAML 2.0** -- Most enterprise identity providers
- **Google Workspace** -- OAuth-based
- **Microsoft Entra ID** -- Formerly Azure AD
- JIT (Just-in-Time) provisioning or manual pre-invitation
- Option to enforce SSO-only login (disable passwords)

## Email & Calendar Integration

### Email Sync
- **Providers:** Google (Gmail), Microsoft (Outlook), generic SMTP
- **Sync speed:** ~400 messages/minute
- **Features:** Folder selection, visibility controls, auto-contact creation, domain-based company linking
- **Update frequency:** Every 5 minutes after initial import
- **Limitations:** Single recipient for workflow emails, no HTML signatures, no CC address option

### Calendar Sync
- **Providers:** Google Calendar, Microsoft Calendar, CalDAV
- **Features:** Event visibility controls, auto-contact creation from participants, record linking
- **Update frequency:** Every 5 minutes

## AI Features (Coming Soon)

### AI Chatbot
- Context-aware conversational assistant
- Access to workspace data
- Natural language querying

### AI Agents in Workflows
- Data enrichment and classification
- Multi-step autonomous tasks
- Customizable prompts
- Lead categorization, email drafts, opportunity scoring

### AI Permissions
Managed through existing RBAC system; agents confined to role-defined scope.

## UI Customization

- **Themes:** Light, Dark, System
- **Language:** Multi-language interface
- **Regional settings:** Timezone, date format, time format (12/24h), number format, calendar start day
- **Early Access:** Toggle for beta features
