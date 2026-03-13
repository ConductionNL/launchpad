# Form Variables System

## What Open Forms Does

### Variable Sources
- **Component variables** -- Auto-created from FormIO components in FormDefinition
- **User-defined variables** -- Manually created in admin, not tied to a visible component
- **Static variables** -- System variables (auth data, form metadata, timestamps)

### FormVariable Model
- `key` -- unique identifier within the form
- `name` -- human-readable label
- `source` -- component or user_defined
- `data_type` -- string, number, boolean, array, object, date, datetime, time
- `data_subtype` -- for arrays, the type of array elements
- `initial_value` -- default value (JSON)
- `is_sensitive_data` -- marks variable for special handling during data removal
- `prefill_plugin` + `prefill_attribute` + `prefill_identifier_role` -- prefill configuration
- `service_fetch_configuration` FK -- external API data source for user-defined variables

### SubmissionValueVariable
Runtime storage of variable values during/after form filling:
- `key`, `value` (JSON), `source` (user_input, prefill, logic, dmn, sensitive_data_cleaner)
- `is_initially_prefilled` -- tracks whether value came from prefill
- `pre_registration_status` and `pre_registration_result` -- for component-level pre-registration

### Variable Synchronization
- `FormVariableManager.synchronize_for(form_definition)` keeps variables in sync with FormIO components
- Compares component configuration against existing variables
- Creates, updates, or deletes variables as needed
- Preserves user-configured prefill/service fetch settings

### Service Fetch Configuration
- `ServiceFetchConfiguration` model defines an external API endpoint
- HTTP method, URL, headers, query parameters
- JSON path mapping for extracting values from response
- Used by user-defined variables to fetch data from external services

### Static Variables
Provided by the system, not editable by form designer:
- Authentication data (BSN, KvK, pseudo ID)
- Form metadata (name, ID)
- Submission metadata (public reference, language)
- Timestamps

## Already in Procest

- OpenRegister schema properties (field definitions)
- Object property values (data storage)

## Not Yet in Procest

- **Two-source variable model** -- No distinction between component-derived and user-defined variables
- **Variable-level prefill binding** -- No per-variable prefill plugin configuration
- **Service fetch configuration** -- No declarative external API data fetching per variable
- **Sensitive data marking** -- No per-variable sensitivity flag
- **Static system variables** -- No automatic injection of auth/metadata variables
- **Variable synchronization** -- No automatic sync between form schema and variable registry
- **Pre-registration status per variable** -- No component-level registration tracking
