# Design: own-look-fields

## Decisions

### D1. A colour field is one row

The colour input (hex text, labelled) and its picker (`NcColorPicker` trigger showing the swatch) sit on one row and do
not wrap at the modal's width; under 480 px they may stack. This holds for the accent and background colour of Own look,
and for every other colour field in launchpad (the tenant theme rows of the embed theming were aligned in the same PR).

### D2. The background image is an upload with a preview

Label "Background image", hint "Shown behind this dashboard; leave empty to follow the theme". A styled upload button in
the app's language replaces the browser's file input; after upload a small preview with a Remove action shows. The image
is stored through launchpad's resource upload (REQ-RES) as before; Remove clears the field, so the dashboard follows the
theme again.

## Migration

None.
