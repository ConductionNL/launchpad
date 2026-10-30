---
kind: code
---

# The Zuiddrecht intranet and management showcases

## Why

The LpIntranet and LpManagement boards draw two group dashboards of the demo municipality Zuiddrecht: "Intranet Zuiddrecht", which every colleague may add widgets to, and "Managementinformatie", which everybody may only look at. A demo instance has neither: the only group dashboard is the default one, so the boards are measured against a dashboard that is not theirs or cannot be opened at all.

Showcases are how LaunchPad ships an example dashboard, so the two dashboards become two bundled showcases an administrator installs with `occ launchpad:demo:showcases:install`.

## What changes

- Two organisation showcases, `zuiddrecht-intranet` and `zuiddrecht-management`, in Dutch, with preview images.
- REQ-DEMO-001 lets a showcase that shows a shared intranet be `add_only`: the board draws "u mag widgets toevoegen", and a read-only intranet would contradict it. Every other showcase stays `view_only`.

## Out of scope

The board's mandatory-read announcement ("Ik heb dit gelezen") is not carried: a showcase deliberately drops the `acknowledgement*` fields (PlacementPayloadHydrator), so LpLeesbevestigingen still needs a placement an administrator marks by hand.
