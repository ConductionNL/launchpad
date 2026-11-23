---
capability: chart-presets
status: draft
---

# Chart presets

## Purpose

A pie chart and a graph an author can pick and fill without knowing chart types.

## ADDED Requirements

### Requirement: A pie chart is a donut with its total in the centre (REQ-CHARTP-001)

The `pie-chart` widget SHALL draw a donut with a legend that writes each slice's value, and the total of the slices
in the centre.

#### Scenario: Cases per status

- **GIVEN** a pie chart with In behandeling 138, Wacht op aanvrager 74, Wacht op advies 46 and Afgerond 54
- **THEN** the donut shows four slices, the legend names each with its value, and the centre says Total 312

### Requirement: A graph draws bars and lines (REQ-CHARTP-002)

The `graph` widget SHALL draw bars over time from a register query, and from entered values any number of named
series, each as bars or a line, in one chart with axes and a legend.

#### Scenario: Applications and decisions per month

- **GIVEN** a graph with axis labels April to September, a series "Aanvragen" as bars and "Besluiten" as a line
- **THEN** one chart shows the bars and the line over the six months, with a legend naming both

### Requirement: A chart reads a register query or entered values (REQ-CHARTP-003)

Both forms SHALL offer "A register query", edited by the shared chart form, and "Values you enter". Entered values
SHALL be saved in the placement and SHALL NOT make the chart read a register. A form SHALL not save without a
register query, or without at least one labelled value (pie chart) or axis labels and one named series (graph).

#### Scenario: Entered values

- **WHEN** an author picks "Pie chart", chooses "Values you enter" and enters two labelled values
- **THEN** the chart shows those two slices and reads no register
