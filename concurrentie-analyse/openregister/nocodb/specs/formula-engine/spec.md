---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Formula Engine

## Overview

NocoDB includes a powerful formula engine with 65 functions. Formulas are parsed into an AST (Abstract Syntax Tree) using JSEP, validated with type checking, and executed as SQL expressions on the database side. The formula system supports references to other columns, nested functions, and type inference.

## Formula Functions (65 Total)

### Numeric Functions (22)
- `AVG(value1, value2, ...)` — Average of values
- `ADD(value1, value2, ...)` — Sum of values
- `ABS(value)` — Absolute value
- `CEILING(value)` — Round up to nearest integer
- `FLOOR(value)` — Round down to nearest integer
- `ROUND(value, decimals)` — Round to N decimals
- `ROUNDUP(value, decimals)` — Round up to N decimals
- `ROUNDDOWN(value, decimals)` — Round down to N decimals
- `MOD(value, divisor)` — Remainder after division
- `POWER(base, exponent)` — Power
- `SQRT(value)` — Square root
- `LOG(value)` — Natural logarithm
- `EXP(value)` — e^value
- `MIN(value1, value2, ...)` — Minimum
- `MAX(value1, value2, ...)` — Maximum
- `COUNT(value1, value2, ...)` — Count non-empty values
- `COUNTA(value1, value2, ...)` — Count all including empty
- `COUNTALL(value1, value2, ...)` — Count all values
- `INT(value)` — Integer part
- `EVEN(value)` — Round up to nearest even number
- `ODD(value)` — Round up to nearest odd number
- `VALUE(text)` — Convert text to number

### String Functions (16)
- `CONCAT(text1, text2, ...)` — Concatenate strings
- `LEFT(text, count)` — Left substring
- `RIGHT(text, count)` — Right substring
- `MID(text, start, count)` — Substring from position
- `SUBSTR(text, start, count)` — Alias for MID
- `LEN(text)` — String length
- `LOWER(text)` — Lowercase
- `UPPER(text)` — Uppercase
- `TRIM(text)` — Remove whitespace
- `REPEAT(text, count)` — Repeat string N times
- `REPLACE(text, old, new)` — Replace substring
- `SEARCH(needle, haystack)` — Find position of substring
- `REGEX_EXTRACT(text, pattern)` — Extract regex match
- `REGEX_MATCH(text, pattern)` — Test regex match
- `REGEX_REPLACE(text, pattern, replacement)` — Replace regex matches
- `URLENCODE(text)` — URL-encode text

### Date Functions (10)
- `DATEADD(date, count, unit)` — Add time to date
- `DATESTR(date)` — Format date as YYYY-MM-DD
- `DATETIME_DIFF(date1, date2, unit)` — Difference between dates
- `DAY(date)` — Extract day (1-31)
- `MONTH(date)` — Extract month (1-12)
- `YEAR(date)` — Extract year
- `HOUR(datetime)` — Extract hour
- `WEEKDAY(date)` — Day of week
- `NOW()` — Current date/time
- `LAST_MODIFIED_TIME()` — Record's last modified timestamp

### Logical Functions (10)
- `IF(condition, then, else)` — Conditional
- `SWITCH(expr, pattern1, value1, ..., default)` — Multi-way conditional
- `AND(expr1, expr2, ...)` — Logical AND
- `OR(expr1, expr2, ...)` — Logical OR
- `XOR(expr1, expr2)` — Exclusive OR
- `TRUE()` — Boolean true
- `FALSE()` — Boolean false
- `ISBLANK(value)` — Check if empty
- `ISNOTBLANK(value)` — Check if not empty
- `BLANK()` — Empty value

### Array Functions (4)
- `ARRAYCOMPACT(array)` — Remove empty values from array
- `ARRAYSLICE(array, start, end)` — Slice array
- `ARRAYSORT(array)` — Sort array values
- `ARRAYUNIQUE(array)` — Unique values from array

### Other Functions (3)
- `RECORD_ID()` — Current record's ID
- `JSON_EXTRACT(json, path)` — Extract value from JSON
- `URL(text)` — Validate and format URL

## Architecture

### Parsing
- Uses JSEP (JavaScript Expression Parser) for AST generation
- Custom node types: Literal, Identifier, CallExpression, BinaryExpression
- Column references use `{ColumnName}` syntax

### Type Inference
- `FormulaDataTypes`: NUMERIC, STRING, DATE, BOOLEAN, COND_EXP, ARRAY
- Each function declares its return type
- Type mismatches are caught during validation

### SQL Execution
- Formulas are transpiled to database-specific SQL
- `functionMappings/` directory contains DB-specific implementations
- Different SQL syntax for MySQL vs PostgreSQL vs SQLite

### Validation
- Custom validation per function (e.g., DATEADD requires date, number, unit)
- Error types: TYPE_MISMATCH, CIRCULAR_REFERENCE, INVALID_COLUMN

## Relevance to OpenRegister

OpenRegister currently has no formula engine. NocoDB's approach offers lessons:
1. **AST-based parsing** is robust and allows SQL transpilation
2. **65 functions** cover most spreadsheet use cases
3. **Type inference** prevents runtime errors
4. **Array functions** work with linked record data (HasMany/ManyToMany)
5. For OpenRegister, n8n workflows could provide similar computed field functionality
