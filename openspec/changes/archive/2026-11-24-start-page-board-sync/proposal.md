# Proposal: start-page-board-sync

## Why

Board LpVandaag draws the start page with one control in the header, the menu Meer as board LpMeer draws it, and
"geen knop om de startpagina aan te passen". The spec `lpvandaag-start-page` still requires a "Startpagina aanpassen"
button and an older Meer menu with a Launchpad group and Versies and Instellingen under Dit dashboard. That
requirement contradicts runtime-shell REQ-SHELL-013, which the code follows.

Two things the board draws are built but not specced:

- the header greets the reader: "Goedemiddag, Pieter · maandag 5 oktober 2026" (board LpStart, LpVandaag), with the
  week number on the start page;
- Vandaag eerst draws one card per record that needs attention, named by the record, with the reason and an action
  such as "Zaak openen" (LpVandaag shows two cards with "Zaak openen", not one count). Built on development in
  7409fd00 (`content.itemCards`).

## What changes

- `lpvandaag-start-page`: the Meer requirement defers to REQ-SHELL-013 and drops "Startpagina aanpassen". A new
  requirement for the greeting in the start page header.
- `attention-feed`: a new requirement for one card per record (`itemCards`).

## Impact

- The greeting and the item cards exist; their tasks are tests and spec tags. The Meer change removes nothing from
  the code, which already follows REQ-SHELL-013.
