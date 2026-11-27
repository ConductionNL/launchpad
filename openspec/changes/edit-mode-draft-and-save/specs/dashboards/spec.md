# Delta: dashboards

## REMOVED Requirements

### Requirement: REQ-DASH-038 The edit bar

**Reason**: Ruben's review of 27 Nov 2026, item 10: edit mode keeps a draft and stores it on Save; Done and the hint
that changes are saved straight away go (board LpBewerken). Replaced by REQ-EDIT-001 to REQ-EDIT-004.

**Migration**: none. Columns, Add widget and Dashboard settings stay in the new edit bar.

## ADDED Requirements

### Requirement: The edit bar holds a draft (REQ-EDIT-001)

While a dashboard is in edit mode, a bar MUST run across the top of the dashboard (board `launchpad/LpBewerken`), as a
toolbar named "Edit dashboard". It MUST say which dashboard is being edited and MUST hold, in this order:

- the draft pill: "Draft · n changes" (nl "Concept · n wijzigingen") with the number of changed things (each added,
  removed, moved or resized, or reconfigured widget once, a changed column count once), or "No changes";
- **Columns** 6, 8 and 12, a group of toggle buttons (`aria-pressed`) with the current count pressed; choosing one
  changes the draft, and the server MUST refuse any other count with 400 at Save;
- **Add widget**, which opens the widget picker;
- **Dashboard settings** (`aria-haspopup="dialog"`), for the owner and whoever may manage the publication; the window
  has its own Save and is not part of the draft;
- **Cancel** and **Save** (nl Annuleren, Opslaan). Save MUST be disabled while there are no changes.

While edit mode lasts, every change to the dashboard's widgets and columns MUST change only the draft: no widget or
dashboard request MUST be sent until Save. The bar MUST NOT say that changes are saved straight away. While the bar is
shown, the page's More menu docks at its end, so the floating menu never covers Save.

#### Scenario: Edit mode shows the draft bar

- **GIVEN** the owner of a dashboard
- **WHEN** they choose More, then Edit
- **THEN** the edit bar MUST show the dashboard's name, "No changes", Columns 6, 8 and 12, Add widget, Dashboard
  settings, Cancel and a disabled Save
- **AND** it MUST NOT show Done or a line that changes are saved straight away

#### Scenario: A move is a draft change

- **GIVEN** the owner in edit mode
- **WHEN** they move a widget to another cell
- **THEN** the pill MUST read "Draft · 1 change", Save MUST be enabled, and no request to store the widget MUST have
  been sent

#### Scenario: Columns change the draft

- **GIVEN** the owner in edit mode on a dashboard with 12 columns
- **WHEN** they choose 8
- **THEN** the grid MUST show 8 columns and 8 MUST be pressed, and the dashboard MUST still store 12 until Save

### Requirement: Save stores the whole draft at once (REQ-EDIT-002)

Save MUST send the draft in one request (`PUT /api/dashboard/{id}` with `gridColumns` and the full `placements` list,
or the group dashboard's equivalent) and leave edit mode when it succeeds. The server MUST check every change against
the person's permission on the dashboard before writing anything, MUST store all changes in one transaction, and MUST
store nothing when one change is refused (403) or fails. A refused or failed save MUST keep the person in edit mode with
the draft intact and say why. When another person changed a widget of the draft after the draft began, the server MUST
answer 409 naming those widgets, and the page MUST let the person keep their version or take the other one per widget;
nothing MUST be replaced silently.

#### Scenario: Save stores and leaves edit mode

- **GIVEN** the owner moved one widget and added the clock widget in edit mode
- **WHEN** they choose Save
- **THEN** one save request MUST be sent, edit mode MUST end, and after a reload the widget MUST be in its new cell and
  the clock MUST be on the dashboard

#### Scenario: A refused change stores nothing

- **GIVEN** a member with add-only permission removed a compulsory widget and added a text widget
- **WHEN** they choose Save
- **THEN** the server MUST answer 403, neither change MUST be stored, and the member MUST stay in edit mode with the
  draft

### Requirement: Leaving with unsaved changes asks first (REQ-EDIT-003)

When the person leaves edit mode or the dashboard while the draft has changes, by any way other than Save or Cancel
(choosing another dashboard, following a link, Escape on the edit bar), the page MUST ask "Save changes?" (nl
"Wijzigingen opslaan?") with Don't save, Keep editing and Save (nl Niet opslaan, Verder bewerken, Opslaan), drawn under
the edit bar (board LpBewerken). Don't save MUST drop the draft and go on, Keep editing MUST stay, Save MUST store as in
REQ-EDIT-002 and then go on. A reload or closing the tab with changes MUST trigger the browser's leave warning.

#### Scenario: Switching dashboards with changes

- **GIVEN** the owner moved a widget in edit mode
- **WHEN** they open More and choose another dashboard
- **THEN** "Save changes?" MUST open with Don't save, Keep editing and Save
- **AND** Don't save MUST open the other dashboard with the move not stored

### Requirement: Cancel discards the draft (REQ-EDIT-004)

Cancel MUST leave edit mode without storing anything. With changes in the draft it MUST first ask "Discard changes?"
with Discard and Keep editing; without changes it MUST leave at once. After Cancel the dashboard MUST show exactly what
was stored before edit mode began.

#### Scenario: Cancel brings the old layout back

- **GIVEN** the owner removed a widget in edit mode
- **WHEN** they choose Cancel and then Discard
- **THEN** edit mode MUST end, the widget MUST be back in its cell, and no request to remove it MUST have been sent
