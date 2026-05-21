# Header Widget

## Why

MyDash users need a way to prominently display banner content — introductions, announcements, branding, welcome messages — across the full width of a dashboard without resorting to markdown hacks or ad-hoc admin templates. Today there is no first-class header widget. The legacy "header row" prototype never shipped as a typed registry entry, leaving users to cobble together markdown or iframes to achieve this. This change formalizes a dedicated `header` widget type with configurable title, optional subtitle, background images (external URL or Nextcloud file), overlay effects, styling, and an optional call-to-action button. The widget fits neatly into the existing dashboard renderer as another discriminated type in the widget placements JSON, with no new database migrations or complex backend logic.

## What Changes

- Add a new widget type `header` registered in `src/constants/widgetRegistry.js` with defaults `{title: '', subtitle: '', backgroundImageUrl: null, backgroundImageFileId: null, backgroundColor: 'var(--color-primary)', overlayMode: 'tint', overlayColor: null, overlayOpacity: 0.4, textColor: null, textAlign: 'center', verticalAlign: 'middle', height: 'medium', cta: null}`.
- Implement renderer `src/components/Widgets/Renderers/HeaderWidget.vue` that composes background images (with fallback), overlay layers, semantic text markup (`<h2>` for title, `<p>` for subtitle), and optional button CTA.
- Build add/edit form `src/components/Widgets/Forms/HeaderForm.vue` offering title input (required), subtitle input, background image picker (file upload or URL), background color picker, overlay controls (mode, color, opacity), text styling (color, alignment), height preset selector, and CTA button configuration.
- Image source precedence: file ID (`backgroundImageFileId`) takes priority over URL (`backgroundImageUrl`); both can be null (solid color fallback).
- Overlay modes: `'none'` (no overlay), `'tint'` (semi-transparent solid color), `'gradient-bottom'` (linear gradient from transparent to color).
- Admin setting `mydash.header_widget_allowed_image_domains` controls external image URL allow-list (JSON array of hostnames); empty/null = all domains allowed (default).
- File ID image references are validated at render time for read ACL; broken/inaccessible files gracefully fall back to backgroundColor.
- External image load failures (404, timeout, invalid MIME) degrade gracefully to backgroundColor without error UI.
- Text color auto-contrasts from backgroundColor when null (light backgrounds → dark text, dark → light).
- Height presets map to fixed pixel heights: `small=120px`, `medium=200px`, `large=320px`, `xlarge=480px`.
- Grid sizing: header widgets default to full dashboard width (gridColumns) with height auto-calculated from the preset.
- CTA button styling (`primary`, `secondary`, `ghost`), URL handling (external URLs open in new tab with `rel="noopener noreferrer"`; internal URLs navigate in-tab), and optional display.
- Accessibility: semantic HTML, keyboard navigation, ARIA labels for CTA buttons, screen reader compatibility.
- Print-friendly: background images and overlays render when the user prints (subject to browser "Print backgrounds" setting).

## Capabilities

### New Capabilities

- `header-widget` — adds REQ-HDR-001 (widget registration), REQ-HDR-002 (placement config structure), REQ-HDR-003 (image source precedence), REQ-HDR-004 (overlay rendering modes), REQ-HDR-005 (external image allow-list), REQ-HDR-006 (file ID image with ACL validation), REQ-HDR-007 (image load failure graceful fallback), REQ-HDR-008 (text rendering and styling), REQ-HDR-009 (height presets and responsive sizing), REQ-HDR-010 (call-to-action button rendering and navigation), REQ-HDR-011 (accessibility for header widgets), REQ-HDR-012 (print-friendly rendering).

### Modified Capabilities

(none — this is a self-contained renderer + form + registry entry; existing widget capabilities are untouched.)

## Impact

**Affected code:**

- `src/components/Widgets/Renderers/HeaderWidget.vue` — new renderer with props `content`, `placement`
- `src/components/Widgets/Forms/HeaderForm.vue` — new add/edit sub-form with all configuration fields
- `src/constants/widgetRegistry.js` — register `type: 'header'` with defaults
- Translation entries: all visible labels and CTA button text (both `nl` and `en` per the i18n requirement)

**Affected APIs:**

- No new MyDash backend routes. The form leverages existing resource-uploads endpoint (via `resource-uploads` capability) for file upload, and Nextcloud's standard file preview route for file ID images.

**Dependencies:**

- `resource-uploads` capability — owns the `/api/resources` upload endpoint that the form's file-input branch uses. The header-widget change MUST land after `resource-uploads` is in place, OR ship behind a feature flag.
- No new composer or npm dependencies. Icons come from the existing `@mdi/svg` icon set.

**Migration:**

- No database migration. Widget content is stored in the existing `oc_mydash_widget_placements.content` JSON blob. Old placements without `type: 'header'` are unaffected.

**Out of scope:**

- Advanced image cropping / aspect ratio control (a future change can add this).
- Animated backgrounds or video backgrounds.
- Configurable grid sizing per placement (header always spans full width; height is determined by the preset).
