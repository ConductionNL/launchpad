# Map widget

## ADDED Requirements

### Requirement: REQ-MAP-001 A map widget shows register objects that carry a location

LaunchPad SHALL offer a `map` widget type that renders the GeoJSON geometry of OpenRegister objects (points, lines, polygons and multi-geometries) on a PDOK BRT base map, fetched through OpenRegister's spatial-query API.

#### Scenario: Objects with a location appear on the map

- GIVEN a map widget configured with a register, a schema and the schema's geo property
- WHEN the dashboard loads
- THEN every object the user may read that has a value in the geo property SHALL be drawn on the map
- AND the widget SHALL show a loading state while the query runs and an empty state when no object has a location

#### Scenario: Dense point sets are clustered

- GIVEN a map widget with a clustering threshold
- WHEN more points fall in one area than the threshold
- THEN the widget SHALL draw them as one cluster that splits when the user zooms in

### Requirement: REQ-MAP-002 A map widget configuration is validated on save

Saving a `map` widget SHALL be refused unless the configuration names `register`, `schema` and `geoProperty`; `spatialFilter` (none, bbox or polygon) and `clusterThreshold` are optional.

#### Scenario: A map widget without a geo property is refused

- GIVEN a dashboard author configuring a map widget
- WHEN they save it without choosing a geo property
- THEN the save SHALL be refused with a message that names the missing field

### Requirement: REQ-MAP-003 Clicking a feature opens its object

Clicking a feature on the map SHALL open the underlying OpenRegister object through the deep-link registry.

#### Scenario: Open an object from the map

- GIVEN a map widget showing objects
- WHEN the user clicks one feature
- THEN the object's deep link SHALL open in a new tab
