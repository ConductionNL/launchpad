---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Theme System

## Purpose

NocoBase's theme editor (`plugin-theme-editor`) allows administrators to customize the visual appearance of the application using Ant Design's token-based theming system. It ships with 4 built-in themes and supports creating custom themes.

## Architecture Overview

The theme system builds on Ant Design 5's design token architecture:

```
Theme Configuration (JSON tokens)
    |
    v
Ant Design ConfigProvider
    |
    v
CSS Variables / CSS-in-JS
    |
    v
Component Rendering
```

## Data Model

### Themes Collection
- `name` - Theme identifier
- `config` - Ant Design theme token configuration (JSON)
- `default` - Whether this is the active theme
- `optional` - Available for user selection

### Token Categories
- **Color tokens** - Primary color, success/warning/error colors, background, text
- **Size tokens** - Font sizes, padding, margin, border radius
- **Typography** - Font family, line height, font weight
- **Motion** - Animation duration, easing functions
- **Shadow** - Box shadow configurations

## Business Logic

### Built-in Themes
1. **Default** - Light theme with blue primary color
2. **Dark** - Dark background with inverted colors
3. **Compact** - Reduced spacing for dense information display
4. **Compact Dark** - Combined compact + dark

### Theme Customization
Administrators can:
- Edit existing themes via the theme editor UI
- Create new themes from scratch
- Set a default theme for all users
- Mark themes as "optional" for user selection
- Duplicate existing themes as starting points

### CSS Variable Integration
The `css-variable` module in `@nocobase/client` maps Ant Design tokens to CSS custom properties, enabling theme-aware styling beyond Ant Design components.

## Requirements

### Functional
- Visual theme editor with live preview
- 4 built-in themes (light, dark, compact, compact-dark)
- Custom theme creation
- Per-user theme selection
- Default theme for all users

### Non-functional
- Theme switching without page reload
- Consistent theming across all components
- Theme token persistence in database

## UI Reference

See screenshot: `13-theme-editor.png`

## Comparison Notes

### vs NL Design System (OpenRegister)
- NocoBase uses Ant Design tokens; OpenRegister uses NL Design System CSS variables
- NocoBase has 4 built-in themes; NL Design supports municipal/organizational theming
- NocoBase themes are UI-level only; NL Design is a full design system with guidelines
- NocoBase has a visual theme editor; NL Design tokens are configured in JSON/CSS files
- NL Design focuses on government accessibility (WCAG); NocoBase prioritizes visual customization
- Both use CSS custom properties as the underlying mechanism
