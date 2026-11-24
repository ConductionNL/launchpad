# Proposal: phone-hidden-widgets-named

## Why

Board LpMobiel draws the notice on a phone as "2 widgets werken niet op een telefoon en zijn verborgen: Snel beginnen
(Word) en Assistent." with a link "Welke?". REQ-MRA-003 asks for a count and a "view desktop layout" link instead, so
the person never learns which widgets are missing. REQ-MRA-003 is not built yet, so the builder can follow the board
from the start.

## What changes

- `launchpad-mobile-remote-access`: the notice names the hidden widgets and "Which?" points to each on demand
  (REQ-MRA-007). The hiding rule of REQ-MRA-003 stays; its "view desktop layout" link is no longer offered.

## Impact

- The notice in the workspace shell, translations.
