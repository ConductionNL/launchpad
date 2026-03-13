# Analytics & Tracking

## What Open Forms Does

### Analytics Plugins
Plugin-based analytics integration:
- Google Analytics
- Matomo (formerly Piwik)
- Piwik
- Piwik PRO
- Piwik PRO Tag Manager
- SiteImprove
- GovMetric (citizen satisfaction)
- ExPoints

### Configuration
- Global enable/disable per analytics tool
- Analytics code snippets injected into form pages
- CSP (Content Security Policy) headers managed per analytics tool
- Cookie consent integration

### Submission Statistics
- `FormSubmissionStatistics` model tracks submission counts over time
- Used for admin dashboard and reporting

### Logging
- Structured logging via `structlog`
- Audit logging for registration events
- Timeline logs for submission lifecycle events
- Admin-visible log entries

## Already in Procest

- Basic Nextcloud logging
- No analytics integration

## Not Yet in Procest

- **Analytics plugin system** -- No pluggable analytics tool integration
- **Google Analytics / Matomo integration** -- No web analytics for form pages
- **GovMetric satisfaction surveys** -- No citizen satisfaction measurement
- **Submission statistics** -- No time-series submission count tracking
- **Audit logging for submissions** -- No structured audit trail per submission
