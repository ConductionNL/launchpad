# Proposal: chart-presets

## Why

Board LpManagement (design-system #1192) shows two charts: "Cirkeldiagram" (a donut with a legend and the total in the
centre) and "Grafiek" (bars for applications, a line for decisions, with axes and a legend). Both read a register
query, or values an author enters.

## What changes

Launchpad already has the chart stack: the `chart` type renders through `ChartHost` over nextcloud-vue's
`CnChartWidget`, which draws donut, bar and line charts from an OpenRegister query. So no second chart stack is built.

- Two types on that stack, `pie-chart` ("Pie chart") and `graph` ("Graph"), each with its own preset.
- `ChartSourceForm`: the data source is "A register query" (the shared `CnChartWidgetForm`) or "Values you enter":
  label, value and colour per slice for a pie chart; axis labels and named series, each drawn as bars or a line, for a
  graph.
- `ChartHost` passes `legendValues`, `legendPosition` and `colorMap` to `CnChartWidget`, writes the donut total in the
  centre when `showTotal` is set, draws entered bars and lines as one mixed chart, and reads no register for entered
  values.

## Limits

- The legend shows each slice's value; `CnChartWidget` has no percentage in the legend, and its tooltip shows the share.
- A register-query graph draws one series per interval. Bars and a line from two register fields in one graph need a
  second data source in `CnChartWidget`; entered values can mix them now.
