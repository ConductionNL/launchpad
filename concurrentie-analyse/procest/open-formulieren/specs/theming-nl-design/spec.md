# Theming & NL Design System

## What Open Forms Does

### Theme Model
`Theme` model stores visual configuration:
- `name`, `uuid` -- identification
- `organization_name` -- displayed in header/footer
- `main_website` -- back link URL
- `favicon`, `logo` -- uploaded images (SVG supported)
- `email_logo` -- separate logo for email templates (no SVG for email safety)
- `stylesheet`, `stylesheet_file` -- custom CSS (URL or uploaded file)
- `design_token_values` -- JSON field for NL Design System token overrides
- `classname` -- NL Design System theme class (e.g., `utrecht-theme`)

### NL Design System Integration
- Forms render within an NL Design System theme context
- The `classname` field sets the root CSS class
- `design_token_values` allows overriding specific design tokens per theme
- Stylesheets can be external URLs or uploaded CSS files
- Multiple themes can exist; forms can select a specific theme or use the global default

### Per-Form Theme
- `Form.theme` FK allows overriding the global theme per form
- Enables different visual identities for different departments/products

### Global Configuration
- `GlobalConfiguration` model has default theme settings
- Fallback for forms without specific theme
- Also controls: organization name, main website, favicon, logo

### SDK Rendering
- Open Forms SDK (React) applies the theme
- CSS custom properties from NL Design System tokens
- Components use Utrecht Design System components

## Already in Procest

- NL Design System token support in `nldesign` app
- CSS variable-based theming in Nextcloud
- Theme configuration per organization

## Not Yet in Procest

- **Per-form theme selection** -- Procest doesn't have forms to theme individually
- **Design token JSON overrides** -- No JSON-based token override per theme (nldesign uses a different approach)
- **Theme SDK rendering** -- No citizen-facing SDK that applies themes
- **Email-specific logo** -- No separate logo configuration for email templates
- **External stylesheet URL** -- No option to load CSS from external URL per theme
