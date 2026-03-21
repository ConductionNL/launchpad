---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Formula System

## Summary

Baserow has a full formula engine using ANTLR4 for grammar parsing, an AST (Abstract Syntax Tree) representation, a type system, and SQL expression generation. Formulas are compiled into PostgreSQL expressions for efficient server-side computation.

## Architecture

```
formula/
  BaserowFormula.g4          # ANTLR4 grammar definition
  parser/                    # ANTLR4-generated parser + custom AST mapper
    ast_mapper.py            # Maps parse tree to Baserow AST
  ast/
    tree.py                  # AST node types (literals, field refs, function calls)
    function.py              # Base function class
    function_defs.py         # All built-in function definitions
    visitors.py              # AST visitor pattern
  types/
    formula_type.py          # Base formula type + invalid/valid type classes
    formula_types.py         # Concrete formula types (text, number, date, etc.)
    type_checker.py          # Type checking and inference
    typer.py                 # AST typing pass
  expression_generator/      # Django ORM expression generation from typed AST
  handler.py                 # High-level formula CRUD operations
  registries.py              # Formula function registry
```

## Formula Language

### Grammar (ANTLR4)
- Located at `formula/BaserowFormula.g4`
- Supports: arithmetic operators, string concatenation, comparison, function calls, field references
- Field references use `field('Field Name')` syntax
- String literals, integer literals, decimal literals, boolean literals

### AST Node Types
- `BaserowStringLiteral`, `BaserowIntegerLiteral`, `BaserowDecimalLiteral`, `BaserowBooleanLiteral`
- `BaserowFieldReference` - reference to another field
- `BaserowFunctionCall` - function invocation with arguments
- `BaserowExpression` - base expression type

### Type System
- `BaserowFormulaInvalidType` - formula has errors
- `BaserowFormulaValidType` - abstract base for valid types
- `BaserowFormulaBaseTextType` - text results
- `BaserowJSONBObjectBaseType` - JSON/array results
- Types flow through the AST via the typer, which infers result types from function signatures and argument types

### SQL Expression Generation
- Typed AST is converted to Django ORM expressions
- These become PostgreSQL expressions in generated SQL
- Enables database-level computation (no Python evaluation per row)
- Supports nested formulas and cross-table references

## Formula Field Types

### Formula Field
- User writes a formula expression
- Stored as formula text + typed AST
- Result type is inferred (text, number, date, boolean, array)
- Read-only computed field

### Count Field
- Extends FormulaFieldType
- Counts linked rows from a LinkRow field
- Simplified UI: select through field only

### Rollup Field
- Extends FormulaFieldType
- Applies aggregate function to values from linked rows
- Supported functions: sum, avg, min, max, count, etc.
- Simplified UI: select through field + target field + function

### Lookup Field
- Extends FormulaFieldType
- Retrieves field values from linked rows
- Returns array of values
- Simplified UI: select through field + target field

## Dependency Tracking

- `FieldDependency` model tracks which fields depend on which
- When a field changes, dependent formula fields are recalculated
- Circular dependency detection
- Cross-table dependency support via link rows

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Formula engine | ANTLR4 grammar + AST + SQL generation | N/A |
| Computed fields | Formula, Count, Rollup, Lookup | N/A |
| Execution | PostgreSQL expressions (database-level) | N/A |
| Dependencies | Automatic dependency graph tracking | N/A |
| Type system | Full inference with error reporting | N/A |

This is one of Baserow's strongest differentiators. The formula system enables spreadsheet-like computed columns with database-level performance.
