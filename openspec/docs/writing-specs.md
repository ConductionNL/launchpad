# Writing Specs

How to write effective specifications that produce good code. Specs are the foundation of the entire workflow — bad specs lead to bad code, no matter how good the AI is.

## Spec Structure

Every spec file follows this structure:

```markdown
# <Capability Name> Specification

## Purpose
<What this capability does and why it exists>

## Requirements

### Requirement: <Name>
<Description using RFC 2119 keywords>

#### Scenario: <Name>
- GIVEN <precondition>
- WHEN <action>
- THEN <expected result>
- AND <additional result>
```

## RFC 2119 Keywords

Use these keywords deliberately to communicate the importance of each requirement:

| Keyword | Meaning | Use when |
|---------|---------|----------|
| **MUST** / **SHALL** | Absolute requirement. Non-negotiable. | The feature won't work correctly without this |
| **MUST NOT** / **SHALL NOT** | Absolute prohibition | Doing this would break something or violate a constraint |
| **SHOULD** | Recommended, but exceptions may exist | Best practice that can be skipped with justification |
| **SHOULD NOT** | Discouraged, but exceptions may exist | Not ideal but acceptable in some cases |
| **MAY** | Optional | Nice to have, up to implementer |

### Examples

```markdown
# Good — clear intention
The API endpoint MUST return HTTP 404 when the resource does not exist.
The response SHOULD include a human-readable error message.
The response MAY include a machine-readable error code.

# Bad — vague, no keywords
The API should handle errors properly.
```

**Rule of thumb:** If you can't decide between MUST and SHOULD, it's probably a SHOULD. If you can't decide between SHOULD and MAY, it's probably a MAY.

## Writing Scenarios

Scenarios use the Gherkin format (GIVEN/WHEN/THEN) to describe specific behaviors. They serve as both documentation and acceptance criteria for implementation.

### Good Scenarios

```markdown
#### Scenario: Successful login with valid credentials
- GIVEN a user with email "test@example.com" and a valid password
- WHEN they submit the login form
- THEN the system MUST return a JWT token
- AND the user MUST be redirected to the dashboard
- AND the session MUST be stored in the database

#### Scenario: Login fails with invalid password
- GIVEN a user with email "test@example.com"
- WHEN they submit the login form with an incorrect password
- THEN the system MUST return HTTP 401
- AND the response body MUST contain `{"error": "Invalid credentials"}`
- AND the failed attempt MUST be logged
```

### Bad Scenarios

```markdown
# Too vague
#### Scenario: Login works
- GIVEN a user
- WHEN they log in
- THEN it works

# Too implementation-specific
#### Scenario: Login
- GIVEN a POST to /api/v1/auth/login with body {"email":"x","pass":"y"}
- WHEN AuthController::login() calls UserService::authenticate()
- THEN it calls $mapper->findByEmail() and JWTService::generate()
```

### Tips for Good Scenarios

1. **Cover the happy path first**, then error cases, then edge cases
2. **Be specific about inputs and outputs** — what data, what status codes, what format
3. **Focus on behavior, not implementation** — describe what happens, not which classes/methods do it
4. **One scenario, one behavior** — don't combine multiple behaviors in one scenario
5. **Include negative scenarios** — what happens when things go wrong?

## Delta Specs

When making changes to existing functionality, use delta specs to show what's changing.

### ADDED

New requirements that didn't exist before:

```markdown
## ADDED Requirements

### Requirement: Full-Text Search
The system MUST support full-text search across publication titles and content bodies using PostgreSQL's tsvector.

#### Scenario: Search returns matching publications
- GIVEN publications with titles "Climate Report 2024" and "Budget Overview"
- WHEN a user searches for "climate"
- THEN the results MUST include "Climate Report 2024"
- AND the results MUST NOT include "Budget Overview"
- AND results MUST be ordered by relevance score
```

### MODIFIED

Changes to existing requirements. Always note what the previous behavior was:

```markdown
## MODIFIED Requirements

### Requirement: Session Duration
The system MUST expire user sessions after 15 minutes of inactivity.

(Previously: sessions expired after 30 minutes of inactivity)

#### Scenario: Session expires
- GIVEN a user who has been inactive for 16 minutes
- WHEN they make a request
- THEN the system MUST return HTTP 401
- AND the session MUST be cleared from the database
```

### REMOVED

Requirements being deprecated. Always explain why:

```markdown
## REMOVED Requirements

### Requirement: Remember Me Checkbox
(Deprecated: replaced by automatic session refresh on activity. Removing the checkbox simplifies the login form and improves security by eliminating long-lived sessions.)
```

## Referencing Shared Specs

When your requirement relates to a cross-project convention, reference the shared spec:

```markdown
### Requirement: Publication API Endpoint
The system MUST provide a REST endpoint at `/index.php/apps/opencatalogi/api/publications`.

See shared spec: `api-patterns/spec.md#requirement-url-structure` for URL conventions.
See shared spec: `api-patterns/spec.md#requirement-cors-support` for CORS requirements.
```

Available shared specs:
- **`nextcloud-app/spec.md`** — App structure, dependency injection, route ordering, config storage, error handling
- **`api-patterns/spec.md`** — URL structure, CORS, authentication, pagination, error responses
- **`nl-design/spec.md`** — Design tokens, theme compatibility, accessibility (WCAG AA)
- **`docker/spec.md`** — Development environment, port mapping, file permissions

## Organizing Specs

### By domain capability

```
openspec/specs/
├── auth/spec.md            # Authentication & sessions
├── publications/spec.md    # Publication CRUD
├── search/spec.md          # Search functionality
├── export/spec.md          # Data export features
└── notifications/spec.md   # User notifications
```

### Tips

- **One capability per spec file** — don't mix unrelated concerns
- **Name directories for the domain concept**, not the implementation (`search/`, not `search-controller/`)
- **Keep specs focused** — if a spec file grows past ~100 requirements, split it
- **Update specs when behavior changes** — specs must always reflect the current system behavior

## Common Mistakes

### 1. Writing specs after code

Specs written after implementation just document what exists. They don't help you think through requirements or catch issues early. **Write specs first.**

### 2. Being too vague

```markdown
# Bad
The system should handle errors.

# Good
The system MUST return HTTP 400 with a JSON body containing an `error` field
when the request body fails validation.
```

### 3. Being too implementation-specific

```markdown
# Bad — tied to specific classes
The AuthController MUST call UserMapper::findByEmail().

# Good — describes behavior
The system MUST look up users by email address during authentication.
```

### 4. Missing error scenarios

Always consider: what happens when the input is invalid? When the resource doesn't exist? When the user isn't authorized? When an external service is down?

### 5. Using MUST for everything

If everything is MUST, nothing is distinguishable. Reserve MUST for true requirements and use SHOULD/MAY for less critical behaviors.

### 6. Writing untestable requirements

```markdown
# Bad — how do you verify this?
The system MUST be fast.

# Good — measurable
The search endpoint MUST respond within 500ms for queries returning fewer than 100 results.
```

## Task Breakdown

When writing `tasks.md`, each task should:

1. **Be completable in one focused iteration** (15-30 minutes)
2. **Have a clear `spec_ref`** pointing to the specific requirement
3. **List `files_likely_affected`** to scope the work
4. **Include `acceptance_criteria`** extracted from spec scenarios
5. **Be ordered by dependency** — foundations first, features second, polish third

### Good task breakdown

```markdown
### Task 1: Create SearchService with basic query method
- **spec_ref**: `openspec/specs/search/spec.md#requirement-full-text-search`
- **files**: `lib/Service/SearchService.php`
- **acceptance_criteria**:
  - GIVEN a search query WHEN SearchService::search("test") is called THEN it returns matching objects
- [ ] Implement
- [ ] Test

### Task 2: Create SearchController with GET endpoint
- **spec_ref**: `openspec/specs/search/spec.md#requirement-search-api-endpoint`
- **files**: `lib/Controller/SearchController.php`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN a GET request to /api/search?q=test THEN returns JSON array of results
- [ ] Implement
- [ ] Test

### Task 3: Add pagination to search results
- **spec_ref**: `openspec/specs/search/spec.md#requirement-search-pagination`
- **files**: `lib/Service/SearchService.php`, `lib/Controller/SearchController.php`
- **acceptance_criteria**:
  - GIVEN 50 results WHEN requesting page=2&limit=10 THEN returns results 11-20 with total count
- [ ] Implement
- [ ] Test
```

### Bad task breakdown

```markdown
### Task 1: Implement search
- [ ] Do everything
```
