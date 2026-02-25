# NL Design System Compliance

## Purpose
Ensures all apps comply with Dutch government design standards via the NL Design System and nldesign Nextcloud app.

## Requirements

### Requirement: Design Token Usage
All visual styling MUST use CSS variables from design tokens, NOT hardcoded values.

#### Scenario: Component uses color
- GIVEN a UI component that needs styling
- WHEN colors are applied
- THEN it MUST use CSS variables (e.g., `var(--utrecht-button-primary-action-background-color)`)
- AND it MUST NOT use hardcoded hex/rgb values

### Requirement: Theme Compatibility
All apps MUST render correctly with any supported NL Design System token set.

#### Scenario: App renders with Rijkshuisstijl theme
- GIVEN the nldesign app is enabled with Rijkshuisstijl tokens
- WHEN the app is viewed
- THEN all components MUST use the Rijkshuisstijl color palette
- AND all text MUST meet WCAG AA contrast ratios

#### Scenario: App renders with Utrecht theme
- GIVEN the nldesign app is enabled with Utrecht tokens
- WHEN the app is viewed
- THEN all components MUST adapt to Utrecht styling
- AND no hardcoded colors MUST override the theme

### Requirement: Accessibility
All apps MUST meet WCAG AA accessibility standards.

#### Scenario: Keyboard navigation
- GIVEN any interactive element
- WHEN a user navigates with keyboard only
- THEN the element MUST be focusable
- AND focus state MUST be visually indicated
- AND all actions MUST be triggerable via keyboard

#### Scenario: Screen reader support
- GIVEN any page in the app
- WHEN read by a screen reader
- THEN all images MUST have alt text
- AND all form fields MUST have labels
- AND heading hierarchy MUST be logical (h1 → h2 → h3)

### Requirement: Supported Token Sets
The nldesign app MUST support the following token sets:
- Rijkshuisstijl (national government)
- Utrecht
- Amsterdam
- Den Haag
- Rotterdam

#### Scenario: Custom municipality theme
- GIVEN a municipality wants their branding
- WHEN their token set is selected
- THEN all apps MUST adapt to that municipality's design tokens
