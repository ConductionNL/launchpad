# Proposal: announcement-show-until

## Why

Board LpAankondiging draws the form "Nieuwe aankondiging" with "Publiceren op (niet verplicht)" and "Tonen tot
(verplicht)", explained as "Daarna verdwijnt het bericht vanzelf van elk dashboard", a preview line "zichtbaar tot 30
oktober 17:00", and the button "Inplannen" when the publish time lies ahead.

REQ-ANN-001 makes the end time optional, so an announcement without one stays on every targeted dashboard for good.

## What changes

- `announcements`: the end time ("Show until") is required for a new announcement and named in the preview; the
  confirm button reads "Schedule" for a future publish time and "Publish" otherwise. Announcements stored without an
  end time keep their behaviour.

## Impact

- The announcement form and the create and update endpoints, translations.
