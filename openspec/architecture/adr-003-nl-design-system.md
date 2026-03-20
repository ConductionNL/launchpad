# ADR-003: NL Design System for All UI

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design, tasks
**Last updated:** 2026-03-19

## Context

Conduction apps are used by Dutch government organizations that require consistent, accessible interfaces following national design standards. The NL Design System provides a token-based theming architecture that allows each municipality to apply their own branding while maintaining accessibility.

Hardcoded colors, custom component libraries, or non-accessible patterns prevent government adoption and create maintenance burden when design standards change.

## Decision

### Theming
- All UI components MUST use CSS custom properties (variables) from NL Design System tokens for colors, typography, spacing, and borders.
- Apps MUST NOT hardcode color values, font sizes, or spacing values in CSS or inline styles.
- Apps MUST support theme switching via the `nldesign` app's token sets (Rijkshuisstijl, Utrecht, municipality-specific).

### Components
- Apps SHOULD use standard Nextcloud Vue components (`@nextcloud/vue`) as the primary component library.
- Custom components MUST be styled using NL Design token variables, not custom design systems.
- All `<style>` blocks in Vue components MUST use the `scoped` attribute.

### Accessibility
- All UI MUST meet WCAG AA compliance level.
- Interactive elements MUST be keyboard-navigable.
- Form fields MUST have associated labels.
- Color MUST NOT be the sole means of conveying information.
- Images and icons MUST have appropriate alt text or aria-labels.

### Responsive Design
- Apps SHOULD work on viewport widths from 320px to 1920px.
- Critical functionality MUST be accessible on tablet-sized viewports (768px).

## Consequences

- Spec authors MUST NOT specify hardcoded colors or pixel values in UI requirements — use token references instead (e.g., "primary action color" not "#0070f3").
- Design documents MUST reference NL Design token names when specifying visual properties.
- Tasks involving UI work MUST include accessibility verification as acceptance criteria.

## Exceptions

- PDF/document generation (`docudesk`) may use fixed dimensions and colors where NL Design tokens are not applicable to print layouts.
- Admin-only configuration screens MAY use simpler styling but MUST still meet WCAG AA.
