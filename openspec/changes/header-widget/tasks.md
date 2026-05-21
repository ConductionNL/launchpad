# Tasks — header-widget

## Tasks

- [ ] Task 1: Create `src/components/Widgets/Renderers/HeaderWidget.vue` with props `content` + `placement`, persisted shape `{type: 'header', content: {title, subtitle, backgroundImageUrl, backgroundImageFileId, backgroundColor, overlayMode, overlayColor, overlayOpacity, textColor, textAlign, verticalAlign, height, cta}}`, and Vue prop validators on enum-type fields (overlayMode, textAlign, verticalAlign, height, cta style) with fallback to defaults on unknown input
- [ ] Task 2: Render semantic HTML structure: `<div class="header-widget">` wrapper with dynamic height (small=120px, medium=200px, large=320px, xlarge=480px), full dashboard width, background image layer, overlay layer(s), and text content layer
- [ ] Task 3: Implement background image handling with correct source precedence: if `backgroundImageFileId` is set, construct preview URL; otherwise if `backgroundImageUrl` is set and passes allow-list validation and same-origin check, use the URL; otherwise render `backgroundColor` only (REQ-HDR-003, REQ-HDR-005, REQ-HDR-006)
- [ ] Task 4: Implement overlay rendering modes (REQ-HDR-004): `overlayMode='none'` renders no overlay; `overlayMode='tint'` renders a semi-transparent `<div>` with `backgroundColor` or `overlayColor` (fallback to `#000000`), `overlayOpacity`, positioned absolutely over the background image; `overlayMode='gradient-bottom'` renders a CSS linear gradient from transparent (top) to `overlayColor` (bottom) with transition starting at 50% height
- [ ] Task 5: Default `overlayMode` to `'tint'` when `backgroundImageUrl` or `backgroundImageFileId` is set, otherwise `'none'` (REQ-HDR-002)
- [ ] Task 6: Render title as `<h2>` (required, always visible), subtitle as `<p>` when non-empty, with `textAlign` (left/center/right) and `verticalAlign` (top/middle/bottom) applied to the text content container using absolute positioning or flexbox (REQ-HDR-008)
- [ ] Task 7: Auto-contrast text color: when `textColor` is null, detect if `backgroundColor` is light or dark and apply dark (#000000) or light (#ffffff) text respectively; when `textColor` is explicitly set, use that color (REQ-HDR-008)
- [ ] Task 8: Render optional CTA button when `cta` is non-null: display button with label `cta.label`, style class determined by `cta.style` (primary/secondary/ghost), and click handler that navigates to `cta.url` (REQ-HDR-010); if URL is external (http/https and different origin), set `target="_blank"` and `rel="noopener noreferrer"`; if URL is internal (same origin or relative path), navigate in-tab
- [ ] Task 9: Image load error handling with `<img @error>` event handler or `<div style="background-image">` onerror equivalent: gracefully fall back to `backgroundColor` if external image fails to load (REQ-HDR-007)
- [ ] Task 10: Build `src/components/Widgets/Forms/HeaderForm.vue` — title input (required, text), subtitle input (optional, text), background image picker (file upload via resource-uploads OR direct URL input), background color picker (color picker or CSS color input), overlay mode selector (dropdown: none/tint/gradient-bottom), overlay color picker (color picker, optional), overlay opacity slider (0.0–1.0, step 0.1), text color picker (color picker, optional), text alignment selector (dropdown: left/center/right), vertical alignment selector (dropdown: top/middle/bottom), height preset selector (dropdown: small/medium/large/xlarge), CTA button configuration (checkbox to enable, then fields for label, URL, and style selector)
- [ ] Task 11: Form file upload pipeline — file → `FileReader.readAsDataURL` → `POST /api/resources` → on success set `form.backgroundImageUrl` from response; on failure surface inline error message under the upload input and leave `form.backgroundImageUrl` unchanged (REQ-HDR-002)
- [ ] Task 12: Form validation: title MUST NOT be empty; if cta is enabled, both `cta.label` and `cta.url` MUST be non-empty; return array of validation error messages on failure
- [ ] Task 13: Form live preview — display a thumbnail of the header widget below the form with current configuration applied, updating in real-time as the user adjusts fields
- [ ] Task 14: Register `header` in `src/constants/widgetRegistry.js` with defaults `{title: '', subtitle: '', backgroundImageUrl: null, backgroundImageFileId: null, backgroundColor: 'var(--color-primary)', overlayMode: 'tint', overlayColor: null, overlayOpacity: 0.4, textColor: null, textAlign: 'center', verticalAlign: 'middle', height: 'medium', cta: null}`, mapped to the new renderer + form and label `t('Header Banner')`
- [ ] Task 15: Vitest renderer — verify height presets map to correct pixel values; overlay modes render correctly (none=no overlay div, tint=semi-transparent div, gradient-bottom=linear gradient); `textAlign` and `verticalAlign` apply correctly via CSS classes; text color auto-contrast works (light bg → dark text, dark bg → light text); CTA button href and target attributes set correctly for external vs internal URLs; image error handler swaps to backgroundColor fallback; prop validators fallback on unknown enum values
- [ ] Task 16: Vitest form — validate() reports required-title message on empty/whitespace; validate() reports required CTA fields when cta is enabled; upload-error path surfaces inline message and leaves `backgroundImageUrl` untouched; live preview updates on field change
- [ ] Task 17: Playwright — add header widget to a dashboard → set title, subtitle, background color → save → reload → widget displays correctly with text and color; upload background image → preview appears → save → reload → image still visible; set external URL with CTA button → click → opens in new tab with `rel="noopener noreferrer"`; set internal URL with CTA → click → navigates in-tab; fail to load external image → widget displays backgroundColor fallback with title/subtitle visible; test all four height presets render at correct pixel heights; test overlay modes (none, tint, gradient-bottom) render correctly
- [ ] Task 18: Accessibility testing — header widget is keyboard reachable (Tab reaches the widget and CTA button if present); `<h2>` heading is proper semantic markup; CTA button is focusable and activatable via keyboard (Enter/Space); external CTA button has aria-label like "Sign up, opens in new tab"; background image is decorative (aria-hidden or no alt attribute); subtitle is not aria-hidden
- [ ] Task 19: Quality + i18n — ESLint + Stylelint clean; SPDX-in-docblock on every new file; `nl_NL` + `en_US` translations for all visible labels (Header Banner, Title, Subtitle, Background Image, Background Color, Overlay Mode, Overlay Color, Overlay Opacity, Text Color, Text Alignment, Vertical Alignment, Height, Call-to-Action Button, Label, URL, Button Style, Primary, Secondary, Ghost, None, Tint, Gradient Bottom, Left, Center, Right, Top, Middle, Bottom, Small, Medium, Large, XLarge, Failed to upload image, Image failed to load); confirm no new backend routes beyond resource-uploads
- [ ] Task 20: Non-functional requirements — header widget renders and displays images within 500ms; external image fetch timeout after 10 seconds, local file preview timeout after 5 seconds; test in Chrome, Firefox, Safari, Edge for compatibility; verify layout doesn't break on older browsers (fallback to solid backgroundColor); placement deletion doesn't leave orphaned data; widgetContent JSON validates on save; WCAG 2.1 AA color contrast and keyboard navigation; allow-list hostname matching is case-insensitive

## Deduplication Check

- [ ] Search existing MyDash widget implementations (image-widget, link-button-widget, text-display-widget, label-widget) for overlapping patterns: background image handling (resource-uploads integration), overlay effects, text styling, button rendering, ACL validation for file references
- [ ] Verify that HeaderWidget does not duplicate logic already in ImageWidget, LinkButtonWidget, or other renderers — document findings (expected: HeaderWidget combines aspects of both but each component is specialized; no overlap to extract)
- [ ] Confirm no duplicate admin settings (allow-list pattern should match existing security controls elsewhere)

## Verification

`openspec validate` exits clean. Renderer + form round-trip through edit mode with persistence; background images load correctly or degrade to color fallback; overlay modes render visually distinct; text color contrast auto-derives correctly; CTA navigation works for both internal and external URLs; accessibility checklist passes.

## Tests (company-wide ADR-009)

Vitest per Tasks 15–16; Playwright per Task 17; accessibility audit per Task 18. No new backend surface.

## Documentation (company-wide ADR-010)

Changelog entry covering the new header widget type; user-guide screenshot of the form with all configuration options and the rendered result.

## i18n (company-wide ADR-007)

`nl_NL` + `en_US` per Task 19.
