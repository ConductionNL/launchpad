# Tasks — widget-calculated-fields

## 1. Parser and Type Checking (TypeScript)

- [x] Task 1: Define AST node types as TypeScript interfaces (built as JSDoc typedefs plus `isAstNode()` in `src/utils/calculatedFields/ast.js`; the app is JavaScript): `Literal`, `FieldRef`, `CalculatedRef`, `Binary`, `Unary`, `If`, `Call` with complete type definitions and JSON-serializable structure
- [x] Task 2: Implement `CalculatedFieldParser.ts` with `parse(expression: string): AST` that produces deterministic canonical AST; on syntax error throw `ParseError` with column position (REQ-CALC-001)
- [x] Task 3: Implement `TypeChecker.ts` with `inferType(ast: AST, fieldSchema): Type` that validates all operators and function calls at the AST level; on type mismatch throw `TypeError` with operand names (REQ-CALC-002)
- [x] Task 4: Define the allowlist of built-in functions with signatures: `math` (abs, round, ceil, floor, min, max, pow, sqrt, log, sum, avg), `aggregate` (count, countIf, sumIf, avgIf), `date/time` (now, today, dateDiff, dateAdd, formatDate, parseDate), `duration` (durationBetween, durationFormat), `string` (concat, upper, lower, trim, contains, startsWith, endsWith, replace, format), `conditional` (coalesce, ifNull, case), `type coercion` (toNumber, toString, toBool, toDate)
- [x] Task 5: Add allowlist to `config.ts` with per-instance configurability (default: all functions); parser rejects unknown functions at parse time (REQ-CALC-009)
- [x] Task 6: Vitest coverage: 100+ test cases covering all AST node types, all operators, all built-in functions, error cases (REQ-CALC-001, REQ-CALC-002)

## 2. Cycle Detection

- [x] Task 7: Implement `CycleDetector.ts` that builds a dependency graph of all calculated fields on a widget + cross-widget references; run topological sort; throw `CycleError` if cycle detected, naming the cycle (REQ-CALC-006)
- [x] Task 8: Wire cycle detection into the parser so `parseAndValidate(widget)` calls both type-checker and cycle-detector before returning validated AST (REQ-CALC-006)
- [x] Task 9: Vitest: cycle detection rejects self-references, mutual references, and longer cycles; accepts acyclic graphs (REQ-CALC-006)

## 3. TypeScript Evaluator (Client-side)

- [x] Task 10: Implement `CalculatedFieldEvaluator.ts` that takes an AST and a row context (field values from source widget) and evaluates deterministically (REQ-CALC-003, REQ-CALC-005)
- [x] Task 11: Handle all AST node types: literals, field refs (with `source='self'` and `source='widget:<id>'`), binary/unary ops, conditionals, function calls; return typed result or `null` on null propagation (REQ-CALC-005)
- [x] Task 12: Error handling: catch runtime errors (divide by zero, null dereference, function error) and return `{error: message, expression, inputs}` so the widget can display an inline error (REQ-CALC-008)
- [x] Task 13: Cross-widget field refs resolved via the dashboard store's widget data at evaluation time (REQ-CALC-003)
  - Built: `evaluateDashboard()` in `src/utils/calculatedFields/dashboardValues.js` hands each field the other widgets' current data and calculated values, read from the calculated-fields store.
- [x] Task 14: Numeric precision: all math follows IEEE 754 (JavaScript Number), test suite validates equivalence with PHP to within 1e-9 (REQ-CALC-005)
  - Validated: the shared suite holds both evaluators to its expected numbers within 1e-9 (Vitest `evaluator.spec.js`, PHPUnit `CalculatedFieldEvaluatorTest`).

## 4. Shared Test Suite

- [x] Task 15: Create `test/shared-evaluator-test-suite.json` containing 50+ test cases: each case specifies an AST, input data (field values), expected output, and error conditions; suite is language-agnostic JSON
- [x] Task 16: Import and run the shared suite in Vitest (TypeScript evaluator) and PHPUnit (PHP evaluator); both must pass 100% of tests (REQ-CALC-005)
  - Vitest half built: `src/utils/calculatedFields/__tests__/evaluator.spec.js` runs every case of `tests/fixtures/calculated-fields/shared-evaluator-test-suite.json` (98 cases, parse and evaluate); the PHPUnit half is `tests/Unit/Service/CalculatedFields/CalculatedFieldEvaluatorTest.php`, one data-provider case per suite case, all 98 passing.

## 5. Frontend Integration (Vue/Store)

- [x] Task 17: Add `useCalculatedFields()` composable that: (1) watches widget source data, (2) watches dashboard filters, (3) maintains a dependency graph of calculated fields, (4) re-evaluates on any change, (5) returns `{values: Map, errors: Map}` (REQ-CALC-003)
  - Built: `src/composables/useCalculatedFields.js` watches the widget's data and the dashboard filters, reports them to the store and returns `{values: Map, errors: Map}`; the dependency graph is the cycle detector's topological order.
- [x] Task 18: Integrate into `useDashboardsStore.js`: when a widget's data arrives or filters change, re-evaluate all calculated fields on that widget and dependent widgets in topological order (REQ-CALC-003)
  - Built as its own Pinia store, `src/stores/calculatedFields.js` (the app has no `useDashboardsStore.js`): `setWidgets`, `setWidgetData` and `setFilters` re-evaluate every field of the dashboard in topological order; `dependentWidgets()` names the widgets a data change reaches.
- [ ] Task 19: Widget component renders calculated-field values in the appropriate column/cell alongside source fields; if error, display error icon with tooltip showing expression + input value (REQ-CALC-008)
- [ ] Task 20: Filtering/export/drill-down: calculated-field values are included in exports and can be used in drill-down but NOT as filter targets in v1 (out of scope) (REQ-CALC-010)

## 6. Schema and Widget Service (PHP)

- [x] Task 21: Extend `WidgetEntity` schema: add nullable `calculatedFields` JSON column storing the array of field objects (id, name, label, expression, compiledExpression, returnType, evaluationMode, etc.)
- [x] Task 22: Create migration that adds the column to existing widgets with default `[]`
- [x] Task 23: Implement `CalculatedFieldService.php` (server-side) with: (1) `validateAndCompile(widget, expression)` calls the parser and type-checker, (2) `detectCycles(widget)` checks for cycles, (3) `evaluateExpression(ast, row): mixed` evaluates the AST for server-side use (REQ-CALC-001, REQ-CALC-002, REQ-CALC-006)
  - Built: `lib/Service/CalculatedFieldService.php`. The server does not re-parse the source text (design D3): `validateAndCompile()` checks each field's stored `compiledExpression` with `CalculatedFieldAstChecker` (node shapes as `isAstNode()`, built-in and allowlisted functions, argument counts), detects cycles over the widget and its dashboard neighbours, and type-checks in dependency order with `CalculatedFieldTypeChecker` (rule for rule as `typeChecker.js`), setting each field's `returnType`. `tests/fixtures/calculated-fields/type-check-cases.json` holds 60 ASTs with the client type checker's answers, and the PHP checker must give the same type or the same error envelope for each.
- [x] Task 24: Wire `WidgetController::create` and `::update` to validate all calculated fields before persisting via `CalculatedFieldService::validateAndCompile` (REQ-CALC-001, REQ-CALC-002, REQ-CALC-006)
  - Built in `WidgetApiController::updatePlacement`, the endpoint that writes `calculatedFields`; creating a placement (`addWidget`) takes no calculated fields, so there is nothing to check there.
- [x] Task 25: Return parse/type/cycle errors as 400 with body `{error: error_code, message: ..., ...details}` (REQ-CALC-001, REQ-CALC-002, REQ-CALC-006)
  - Built: a `CalculatedFieldValidationError` or `CalculatedFieldCycleError` answers 400 with its envelope (`invalidExpression`, `unknownFunction`, `functionNotAllowed`, `arityMismatch`, `functionSignature`, `typeError`, `duplicateField`, `cycleDetected`, with the `field` named) and nothing is saved. Parse errors with a column stay in the editor, which parses the text.

## 7. OpenRegister Integration (Server-side Materialisation)

- [ ] Task 26: Implement `CalculatedFieldService::materializeForOpenRegister(widget): array` that, for every calculated field with `evaluationMode='server'` or `'both'`: (1) creates/updates a `computed-fields` entry on the schema, (2) populates `serverComputedFieldId` in the widget, (3) stores the expression in OpenRegister (REQ-CALC-004)
- [ ] Task 27: Wire materialisation into `WidgetService::save()`: after widget is persisted, call `materializeForOpenRegister()` in a transaction (REQ-CALC-004)
- [ ] Task 28: On widget delete, call `CalculatedFieldService::cleanupServerFields(widget)` to delete all orphaned `computed-fields` entries (REQ-CALC-004)
- [ ] Task 29: Implement downgrade logic (REQ-CALC-007): when a server-mode field references a widget with a different source register, downgrade to `client` mode, set `evaluationMode='client'`, and return warning in API response

## 8. PHP Evaluator (Server-side)

- [x] Task 30: Implement `CalculatedFieldEvaluator.php` that deserialises the AST (JSON) and evaluates it on a row context (REQ-CALC-005)
  - Built: `lib/Service/CalculatedFields/CalculatedFieldEvaluator.php` evaluates the parser's JSON AST on a row context (`row`, `calculated`, `widgets`, `widgetCalculated`), with `now` injectable.
- [x] Task 31: Mirror the TypeScript evaluator exactly: all AST node types, all operators, all functions; numeric precision via IEEE 754 floats; null propagation (REQ-CALC-005)
  - Built: node for node and function for function as `src/utils/calculatedFields/evaluator.js`: `CalculatedFieldFunctions` (the allowlist, the same failures and messages), `CalculatedFieldValues` (JavaScript's number text, `===` over int and float, path reading across lists, UTC dates and calendar months), null propagation and the null-aware functions.
- [ ] Task 32: Error handling: catch runtime errors and store them in OpenRegister's computed-field error log (not thrown to the widget caller); return `null` for the materialised value (REQ-CALC-008)
  - Partly built: `CalculatedFieldEvaluator::evaluateField()` catches a run-time failure and answers `{value: null, error, expression}` instead of throwing. Left: writing the failure to OpenRegister's computed-field error log, which belongs with the materialisation (tasks 26-28).
- [x] Task 33: Run the shared test suite (Task 16) against the PHP evaluator; all tests must pass (REQ-CALC-005)
  - All 98 cases of `tests/fixtures/calculated-fields/shared-evaluator-test-suite.json` pass against the PHP evaluator.

## 9. Format Options and Rendering

- [x] Task 34: Implement `FormatOptions` type with: number format (style, minimumFractionDigits, maximumFractionDigits, currency, locale), date format (pattern, locale), duration format (pattern)
  - Built: `src/utils/calculatedFields/formatOptions.js` (JSDoc typedefs `NumberFormatOptions`, `DateFormatOptions`, `DurationFormatOptions`).
- [ ] Task 35: In the widget's table/chart/single-value renderers, apply format options at display time ONLY (not at evaluation time) using `Intl.NumberFormat`, `Intl.DateTimeFormat`, or custom logic for durations (REQ-CALC-010)
  - Partly built: the formatters (`Intl.NumberFormat`, `Intl.DateTimeFormat`, a duration pattern) exist; calling them from the table, chart and single-value renderers is left with task 19.
- [x] Task 36: Format options do NOT affect the value passed to alerting, export, or other consumers; only the displayed string in the UI (REQ-CALC-010)
  - `formatCalculatedValue()` only answers a display string; the store keeps native values for alerting, export and drill-down (tested).

## 10. Testing — Backend

- [x] Task 37: PHPUnit — `CalculatedFieldValidationTest.php`: parse errors, type errors, cycle detection, unknownFunction errors all return correct 400 envelope (REQ-CALC-001, REQ-CALC-002, REQ-CALC-006, REQ-CALC-009)
  - Built as `CalculatedFieldAstCheckerTest`, `CalculatedFieldTypeCheckerTest`, `CalculatedFieldServiceTest` and `WidgetApiControllerCalculatedFieldsTest` (the 400 envelopes on the endpoint).
- [x] Task 38: PHPUnit — `CalculatedFieldEvaluatorTest.php`: shared test suite (Task 16) runs against PHP evaluator; all operators, functions, null handling, numeric precision (REQ-CALC-005)
  - `CalculatedFieldEvaluatorTest` (102 tests): the shared suite, `evaluateField()` catching a failure with its expression, cross-widget reads, unknown nodes and functions, wrong kinds.
- [ ] Task 39: PHPUnit — `CalculatedFieldServerModeTest.php`: verify `computed-fields` entries created/updated/deleted correctly; downgrade logic works (REQ-CALC-004, REQ-CALC-007)
- [ ] Task 40: PHPUnit — `CrossWidgetReferenceTest.php`: parse/evaluate cross-widget refs (`widget:id.field`); cycle detection (REQ-CALC-006)
  - Partly built: `CalculatedFieldCycleDetectorTest.php` (7 tests) covers cross-widget cycles and order; cross-widget evaluation is in `CalculatedFieldEvaluatorTest`.

## 11. Testing — Frontend

- [ ] Task 41: Vitest — `parser.test.ts`: 100+ cases covering all AST node types, operators, functions, error cases (REQ-CALC-001, REQ-CALC-002, REQ-CALC-009)
- [x] Task 42: Vitest — `evaluator.test.ts`: shared test suite runs against TypeScript evaluator; all operators, functions, null handling, numeric precision (REQ-CALC-005)
- [x] Task 43: Vitest — `cycleDetector.test.ts`: cycle detection rejects self-refs, mutual refs, longer cycles; accepts acyclic (REQ-CALC-006)
- [ ] Task 44: Playwright — render a widget with calculated fields; verify values appear in the table/chart; verify inline errors show on evaluation failure; verify other widgets still render (REQ-CALC-003, REQ-CALC-008)
- [ ] Task 45: Playwright — cross-widget reference: change filter on source widget, verify dependent widget's calculated field re-evaluates (REQ-CALC-003)
- [ ] Task 46: Playwright — server-mode field: materialised value is queryable via OpenRegister API; client-mode field is not (REQ-CALC-004)

## 12. Quality and Documentation

- [ ] Task 47: `composer check:strict` and `eslint` pass on all new files
- [ ] Task 48: OpenAPI / Postman / API docs: document the new Widget schema extension (`calculatedFields` array); document error response envelopes (REQ-CALC-001, REQ-CALC-002, REQ-CALC-006, REQ-CALC-009)
- [ ] Task 49: i18n — `nl_NL` and `en_US` translations for: error messages (parse_error, type_error, cycle_detected, unknownFunction), inline error hover text, downgrade warning
- [x] Task 50: SPDX — all new PHP files have `@license EUPL-1.2` + `@copyright` in docblock
  - Every new PHP file under `lib/Service/CalculatedFields/` carries `@license EUPL-1.2`, `@copyright` and the SPDX lines.
- [x] Task 51: Changelog entry describing: calculated fields as a widget feature, dual evaluation (client + server), allowlisted function set, error handling + inline indicators, cross-widget refs + cycle detection, format options
  - `CHANGELOG.md` (Unreleased, Added): the evaluation engine, marked in progress until storage and materialisation land.
- [ ] Task 52: Design.md — document the design decisions (D1–D7), rationale for dual evaluation, AST-based approach, shared test suite, format-at-render-time strategy, migration plan
- [ ] Task 53: All hydra-gates pass (route-auth, admin-idor, semantic-auth, spdx-headers, etc.)

## Verification

`openspec validate` exits clean. Widget with calculated field saves and displays correctly on the dashboard. Parse/type/cycle errors are caught at save time and shown to the user with clear messages. Client-side evaluator re-computes on filter change without round-trip. Server-mode field materialises to OpenRegister and is queryable. TypeScript and PHP evaluators produce identical results.

## Tests

- Vitest: parser, type-checker, cycle detector, evaluator (TypeScript), shared test suite
- PHPUnit: validation, evaluator (PHP), server-side integration, cross-widget refs
- Playwright: render, error display, cross-widget reactivity, filter changes

## Documentation

- Changelog: feature overview, dual-evaluation strategy, allowlist, error handling
- Design.md: decisions, rationale, migration plan, seed data
- i18n: error messages, user-facing strings
- OpenAPI: schema extension, error envelopes
