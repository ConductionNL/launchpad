---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Canvas Grid Rendering

## Overview

NocoDB renders its spreadsheet grid using HTML5 Canvas rather than DOM-based tables or virtual scrolling. This is a significant architectural decision that prioritizes rendering performance over accessibility and standard DOM interaction.

## Implementation

### Canvas Element
- Single `<canvas>` element with class `sticky top-0 left-0`
- Dimensions match the grid viewport (e.g., 461x401 pixels)
- Positioned over the grid wrapper area

### Rendering Approach
- **Headers** — Column names, types, icons rendered on canvas
- **Cells** — All cell content (text, badges, checkboxes, ratings) rendered on canvas
- **Row numbers** — Rendered on canvas left edge
- **Selection** — Blue highlight rendered on canvas
- **Borders** — Grid lines rendered on canvas
- **Scroll** — Custom scroll handling, not native browser scroll

### Custom Cell Renderers
Each field type has a canvas-specific renderer:
- **SingleSelect** — Colored badge with text
- **Rating** — Star/heart/flag icons
- **Checkbox** — Check/circle/star icons
- **URL/Email** — Clickable link styling
- **Number/Currency** — Formatted numbers
- **Date** — Formatted dates

## Interaction Model

### Click Handling
- Canvas receives mouse events at (x, y) coordinates
- Hit-testing maps coordinates to (row, column) cells
- Different click zones: cell content, row expand icon, column resize handle, checkbox

### Editing
- Click on cell activates inline editor (DOM overlay on canvas)
- Editor positioned at canvas cell coordinates
- Escape/Tab/Enter to commit or cancel

### Context Menus
- Right-click on column header opens DOM-based dropdown menu
- Right-click on cell opens record context menu
- Menus are standard DOM elements overlaying the canvas

### Row Expansion
- Click on expand icon (arrow) opens expanded row modal
- Modal is DOM-based with form fields

## Trade-offs

### Advantages
1. **Performance** — Can render 10,000+ rows without DOM overhead
2. **Smooth scrolling** — No virtual scroll jank or DOM recycling artifacts
3. **Custom rendering** — Full pixel-level control over cell appearance
4. **Memory efficiency** — No DOM node allocation per cell

### Disadvantages
1. **Accessibility** — Canvas content is invisible to screen readers
2. **DevTools** — Cannot inspect individual cells in browser DevTools
3. **Browser features** — No native text selection, find-in-page, or right-click
4. **Testing** — Cannot use standard selectors for automated testing
5. **Complexity** — Custom hit-testing, rendering, and interaction code

## Relevance to OpenRegister

OpenRegister uses standard DOM-based rendering:
1. **Accessibility** is critical for government applications (WCAG AA)
2. **Canvas is NOT suitable** for OpenRegister's target audience (Dutch municipalities)
3. **Virtual scrolling** (e.g., vue-virtual-scroller) could provide adequate performance
4. **Hybrid approach** possible: canvas for large datasets, DOM for normal views
5. NocoDB's canvas approach shows what's possible for performance optimization
