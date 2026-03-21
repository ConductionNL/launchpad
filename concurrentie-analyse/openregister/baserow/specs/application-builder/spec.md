---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Application Builder

## Summary

Baserow includes a no-code application builder that allows creating full web applications with pages, elements, data sources, workflow actions, and custom domains. Applications can be published and served at custom domains. This is a significant feature differentiator.

## Architecture

Located at `backend/src/baserow/contrib/builder/`

```
builder/
  pages/           # Page management, routing
  elements/        # UI element types (20+ components)
  data_sources/    # Connect to Baserow tables or external APIs
  domains/         # Custom domain publishing
  workflow_actions/ # Actions triggered by user interactions
  theme/           # Theming and style configuration
  data_providers/  # Provide data context to elements
```

## Pages

- Each application has multiple pages
- Pages have URL paths with parameters (e.g., `/products/:id`)
- Pages contain elements arranged in a layout
- Page-level data sources provide data context

## Element Types (20+ Components)

### Layout Elements
- **Column** - Multi-column layout container
- **FormContainer** - Form wrapper with submit behavior
- **SimpleContainer** - Basic wrapper container
- **Header** - Persistent header across pages
- **Footer** - Persistent footer across pages

### Display Elements
- **Heading** - H1-H6 headings with formula-based content
- **Text** - Rich text with formula interpolation
- **Image** - Image display with formula-based src
- **IFrame** - Embed external content
- **Rating** - Star/heart rating display

### Data Elements
- **Table** - Data table with sortable columns, collection fields
- **Repeat** - Repeating layout for list rendering
- **RecordSelector** - Select a record from a data source

### Form Elements
- **InputText** - Text input (text, email, password, number, tel, url, integer types)
- **Checkbox** - Boolean checkbox input
- **Choice** - Dropdown/radio selection with options
- **RatingInput** - Interactive rating input
- **DateTimePicker** - Date and time selection

### Navigation Elements
- **Link** - Navigation link or button with URL/page navigation
- **Button** - Action button triggering workflow actions
- **Menu** - Navigation menu with nested items

## Data Sources

- Connect elements to Baserow tables or external data
- Support filtering, sorting, pagination
- LocalBaserow integration for table queries
- Data context available via formula expressions in elements

## Workflow Actions

Triggered by element interactions (button click, form submit):

1. **NotificationWorkflowAction** - Show notification to user
2. **OpenPageWorkflowAction** - Navigate to another page
3. **LogoutWorkflowAction** - Log out user
4. **RefreshDataSourceWorkflowAction** - Refresh data
5. **CreateRowWorkflowAction** - Create a row in a table
6. **UpdateRowWorkflowAction** - Update an existing row
7. **DeleteRowWorkflowAction** - Delete a row
8. **AIAgentWorkflowAction** - Invoke AI agent
9. **SlackWriteMessageWorkflowAction** - Send Slack message

## Domains and Publishing

- Applications can be published to custom domains
- Domain configuration with SSL
- Published apps are standalone web applications
- Separate from the Baserow admin interface

## Theming

- Theme config blocks for consistent styling
- Color, button, and typography theme settings
- Per-application theme configuration

## User Sources (Authentication)

Located at `backend/src/baserow/core/user_sources/`:
- Configurable user authentication for published apps
- JWT-based app user sessions
- Integration with external auth providers

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| App builder | Full no-code builder with 20+ elements | N/A |
| Page routing | URL params, multi-page | N/A |
| Data binding | Formula expressions in elements | N/A |
| Custom domains | Publish to custom domains | N/A |
| Workflow actions | 9 action types (CRUD, nav, notify) | N/A |
| User auth | Built-in user sources for apps | N/A |
| Theming | Theme config blocks | NL Design tokens |

The application builder is Baserow's most unique feature compared to OpenRegister. It transforms Baserow from a database tool into a full application platform.
