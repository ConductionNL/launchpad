---
competitor: espocrm
analyzed_date: 2026-03-14
feature: formula-engine
---

# Formula Engine

## Overview

EspoCRM includes a built-in formula language for computed fields, validation rules, and before-save business logic. Formulas are written in a custom expression syntax and executed server-side via a PHP-based parser and evaluator. This is available in the open-source edition.

## Architecture

### Core Components (`Core/Formula/`)
- **Parser** - Tokenizer and AST builder with statement handling
- **Evaluator** - Walks the AST and evaluates expressions
- **Functions** - Organized in function groups
- **Exceptions** - Typed error handling

### Function Groups

| Group | Purpose | Example Functions |
|-------|---------|-------------------|
| **StringGroup** | String manipulation | concatenate, substring, contains, replace, lowerCase, upperCase, trim, length |
| **NumberGroup** | Math operations | round, floor, ceil, abs, format |
| **NumericGroup** | Arithmetic | add, subtract, multiply, divide |
| **DatetimeGroup** | Date/time operations | today, now, addDays, addMonths, format, diff |
| **ComparisonGroup** | Comparisons | equals, notEquals, greaterThan, lessThan |
| **LogicalGroup** | Boolean logic | and, or, not, ifThenElse |
| **EntityGroup** | Entity operations | getAttribute, setAttribute, isNew, isAttributeChanged, save |
| **RecordGroup** | Cross-entity queries | findOne, findRelatedOne, findRelatedMany, count, create, update, relate, unrelate |
| **RecordServiceGroup** | Record service operations | Uses service layer for ACL-aware operations |
| **ArrayGroup** | Array operations | push, length, at, includes, join |
| **ObjectGroup** | Object/map operations | get, set, has, keys |
| **JsonGroup** | JSON operations | encode, decode |
| **EnvGroup** | Environment access | userAttribute, config |
| **LanguageGroup** | Translation access | translate, translateOption |
| **LogGroup** | Logging | info, warn, error |
| **OutputGroup** | Output operations | For workflow/action results |
| **PasswordGroup** | Password utilities | generate, hash |
| **UtilGroup** | Utilities | generateId |
| **ExtGroup** | Extension point | For custom functions |

### CRM-Specific Functions (`Modules/Crm/Classes/FormulaFunctions/`)
Additional formula functions specific to CRM operations (in ExtGroup).

## Usage Contexts

### Calculated Fields
Fields can have a formula that computes their value before save:
```
// Field Manager -> Formula -> Before Save Script
ifThenElse(
    stage == 'Closed Won',
    amount,
    amount * probability / 100
)
```

### Before-Save Scripts
Entity-level scripts that run before any save operation, allowing complex business logic:
```
// Entity Manager -> Formula -> Before Save Script
ifThen(
    isAttributeChanged('stage') && stage == 'Closed Won',
    setAttribute('closeDate', today())
);

ifThen(
    isNew() && !accountId,
    setAttribute('assignedUserId', env\userAttribute('id'))
);
```

### API Action (via Controller)
Formula can be executed via API for testing:
```
POST /Formula/action/run
{ "expression": "string\\concatenate('Hello', ' ', 'World')" }
```

### Mass Recalculate
Recalculate formula fields across all records of an entity type (mass action).

## Formula Syntax

The formula uses a function-call syntax (not infix operators):
```
// Variables
$variable = 'value';

// Function calls
string\concatenate(firstName, ' ', lastName)

// Conditionals
ifThenElse(condition, trueValue, falseValue)
ifThen(condition, action)

// Entity access
entity\getAttribute('fieldName')
entity\setAttribute('fieldName', value)

// Cross-entity queries
record\findOne('Contact', 'id', 'emailAddress=', emailAddress)

// Comments
// This is a comment
```

## Formula Metadata (`metadata/app/formula.json`)
Defines available function groups and their function lists, used by the admin UI to show autocomplete and documentation.

## Relevance to Pipelinq

### Strengths
- Powerful server-side computation without custom PHP code
- Rich function library covering strings, dates, math, entity operations
- Cross-entity queries enable complex business logic
- Admin-accessible (no developer needed)
- Mass recalculation for bulk updates

### Weaknesses
- Custom syntax (not JavaScript or a standard language)
- No debugging tools beyond logging
- Synchronous execution only (no async/webhook triggers)
- Limited to before-save context in open-source (workflows require Advanced Pack)

### Opportunities for Pipelinq
- **n8n workflows**: Instead of a custom formula language, Pipelinq uses n8n for business logic, which is more visual and powerful
- **JavaScript expressions**: If computed fields are needed, standard JavaScript is more accessible than a custom formula syntax
- **Schema-level validation**: OpenRegister JSON Schema validation handles what formulas do for field validation
- **Trigger-based**: n8n workflows can trigger on any event (not just before-save), enabling more flexible automation
