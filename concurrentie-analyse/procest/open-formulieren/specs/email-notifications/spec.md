# Email Notifications

## What Open Forms Does

### Email Types
1. **Confirmation email** -- Sent to submitter after successful submission
2. **Payment confirmation email** -- Sent after successful payment
3. **Co-sign request email** -- Sent to co-signer with approval link
4. **Co-sign confirmation email** -- Sent to submitter after co-sign complete
5. **Suspension email** -- Sent with resume link when form is saved for later
6. **Admin digest email** -- Periodic health check to administrators

### Confirmation Email
- Template-based (Django templates with submission variables)
- Can include: submission reference, summary of submitted data, payment status
- Form-specific template overrides global template
- Sent as HTML email with optional PDF attachment
- Registration backend notified of confirmation email via `update_registration_with_confirmation_email()`

### Email Configuration
- Global email templates in `GlobalConfiguration`
- Per-form `submission_confirmation_template` override
- WYSIWYG editor (TinyMCE) for template editing
- Template variables: submission data, form name, public reference, etc.

### Email State Tracking
- `confirmation_email_sent` flag on Submission
- `cosign_request_email_sent`, `cosign_confirmation_email_sent` flags
- `payment_complete_confirmation_email_sent` flag
- Failed emails collected in admin digest

### Email Content Headers
- Custom headers for tracking: `X-OF-Content-Type`, `X-OF-Content-UUID`, `X-OF-Event`
- Used for correlation and debugging

## Already in Procest

- Basic Nextcloud notification system
- n8n can send emails via workflows

## Not Yet in Procest

- **Template-based confirmation emails** -- No automatic submission confirmation with data summary
- **Per-form email templates** -- No form-specific email template overrides
- **Suspension resume links** -- No email with link to resume a saved form
- **Co-sign request emails** -- No out-of-band approval request emails
- **Email state tracking per submission** -- No flags tracking which emails were sent
- **Admin digest emails** -- No periodic health/failure summary emails
- **WYSIWYG email template editor** -- No rich text editor for email templates
