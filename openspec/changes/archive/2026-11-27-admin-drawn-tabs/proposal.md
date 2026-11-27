# Proposal: admin-drawn-tabs

## Why

The Beheer boards (LpBeheerAlgemeen, LpBeheerSjablonen, LpBeheerOnderhoud, LpBeheerRollen, LpBeheerAnalyse,
LpBeheerDelen, LpBeheerNavigatie) draw twelve administration tabs: Algemeen, Sjablonen, Bediening, Rollen en rechten,
Versies en analyse, Delen, Kiosk, Organisatiemenu, Demogegevens, Groepsdashboards, Detailvelden, Profielvelden. The
app showed sixteen: also Embeds, Communities, Search shortcuts and App catalogue. The pixel pass (DONE check 3) flags
the tab strip on every Beheer board (tabCount 12 drawn vs 16 live, strip width 1184 vs 1334). Boards are canon; the
done lane decided (27 Nov 2026, coordinator queue) to fold the four extra tabs into drawn tabs, each being a single
settings block.

## What changes

- The tab strip holds the twelve drawn tabs, in the drawn order.
- Embeds and Search shortcuts become cards of Algemeen; Communities a section of Groepsdashboards; App catalogue a
  section of Bediening. Their content and behaviour do not change.
- An old address (`/apps/launchpad/admin/embeds` and so on), a link into the embeds form and the notifications that
  linked to a former tab open the host tab on that section, with the embed form's pre-selection kept.

## Out of scope

The content of the drawn tabs beyond the tab strip (other pixel differences are their own items).
