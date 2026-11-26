# Proposal: widget-picker-board-sync

## Why

Board LpWidgetKiezen draws "Widget toevoegen", the only place where widgets are added, as a dialog of 720 px: an assistant line at the top ("Beschrijf wat u nodig hebt", a proposal with Plaats het zo), a search field, a column of kinds with counts (Alles 52, Inhoud 12, Tegels en links 17, Mensen 3, Gegevens en grafieken 8, Uit andere apps 10, Office 4), and to its right the widgets as cards in two columns: "Widgets van Launchpad", "App-tegels" (one coloured tile per app of the catalogue, plus Eigen tegel) and "Uit andere Nextcloud-apps". A kind the role may not add is a grey card that says why; an older widget type carries the label "Ouder widgettype". The dialog ends with Annuleren and Widget toevoegen.

On development the picker is nextcloud-vue's `CnAddWidgetModal`: a search and one long list "From the app catalogue" with a plus per row. No requirement describes the kinds, the card grid, the app tile section or the disabled cards, so the stack cannot be brought to the board without a spec.

## What changes

- `widgets`: the picker as the board draws it (REQ-WDG-040), its kinds with counts (REQ-WDG-041), the App tiles section (REQ-WDG-042) and widgets the role may not add (REQ-WDG-043).
- The assistant line reuses `launchpad-ai-dashboard-assistant`; this change only places it at the top of the picker.

## Impact

- `src/modals/WidgetPickerModal.vue` (or a picker body passed to `CnAddWidgetModal`), the role feature permissions it reads, translations.
