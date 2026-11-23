# Proposal: public-live-widgets

## Why

Boards LpPubliek and LpKiosk draw a public start page and a canteen screen that anonymous visitors see: news items with their date, the city office's opening hours as a list with "Open now, until 17:00", outages with a coloured dot, the weather, and the waiting time at the desk. `PublicDashboardGrid` renders only static widgets (label, header, text, image, link, divider) for an anonymous visitor; every other widget says "This widget is only visible to signed-in users", and a text widget's Markdown shows as raw text. So the news, weather and live data cards of both boards cannot show, whatever the look.

## What changes

- `dashboard-public-share`: news, weather and live data widgets render on a public link and on a kiosk screen, read through the link's token (REQ-PSHR-011); a text widget's Markdown is rendered, sanitised (REQ-PSHR-012).

## Impact

- `src/components/PublicDashboardGrid.vue`, token-scoped read endpoints for announcements (published, targeted at everyone), weather and live data, `PublicShareService`/`KioskService` checks, translations.
