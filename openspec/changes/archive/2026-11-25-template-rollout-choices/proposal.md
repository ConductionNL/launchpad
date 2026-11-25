# Proposal: template-rollout-choices

## Why

Board LpBeheerSjablonen draws the window "Wijzigingen doorvoeren naar 212 dashboards". It says what changed since the
previous version ("Sinds versie 13 kwamen de widgets Leesbevestiging en Verjaardagen erbij en verdween Oud nieuws"),
asks "Hoe bijwerken?" with three choices, and counts the people who changed their copy ("38 mensen hebben hun
dashboard aangepast. Hun eigen widgets blijven staan."):

- Alleen nieuwe widgets toevoegen: niets verdwijnt; persoonlijke indeling blijft.
- Persoonlijke aanpassingen behouden: sjabloon wint, behalve wat iemand zelf heeft verplaatst of toegevoegd.
- Alles vervangen door het sjabloon: persoonlijke aanpassingen gaan verloren.

The re-sync (REQ-RESYNC-001 to 005) has two strategies, `overwrite` and `merge`. There is no "add only", merge moves a
template widget back even when the person moved it, and the dry run (REQ-RESYNC-002) reports a plan but no window shows
it in these words.

## What changes

- `admin-templates`: an `add_only` strategy (REQ-RESYNC-006); under `merge`, a template widget the person moved stays
  where they put it (REQ-RESYNC-007); the rollout window with the change summary, the three choices and the count of
  changed copies (REQ-RESYNC-008).

## Impact

- The re-sync service and endpoint (new strategy value), the dry-run summary, the rollout modal on the templates tab,
  translations.
