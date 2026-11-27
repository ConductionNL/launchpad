# Proposal: announcement-frame

## Why
The boards LpVandaag and LpIntranet draw the placement that asks readers to confirm "Nieuwe werkwijze Woo-verzoeken"
as an announcement: a 4px bar in the primary colour on the card's left edge, 24px in on the left and 20px on the
right, a 17px title. Live it was a plain card (compdiff 27 Nov: 9-10 flags on each board). Boards are canon; the done
lane decides that every placement that asks for a read confirmation wears that frame.

## What changes
- `WidgetWrapper` marks a placement with `requiresAcknowledgement` `launchpad-widget--announcement`.
- Board look: that card gets the bar, the insets and the 17px title.

## Not in this change
The board's "Aankondiging" pill with author and time above the title: a text placement has no author or time to show.
