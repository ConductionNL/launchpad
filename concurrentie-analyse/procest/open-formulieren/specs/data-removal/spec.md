# Data Removal & Privacy

## What Open Forms Does

### Removal Methods
- `delete_permanently` -- Entire submission deleted
- `make_anonymous` -- Only sensitive data fields removed, submission record retained

### Configuration
- Per-form retention period
- Configurable removal method per form
- Global defaults in `GlobalConfiguration`

### Data Lifecycle
1. Submission completed and registered
2. Retention period expires
3. Celery beat task identifies expired submissions
4. Based on removal method: delete or anonymize
5. `_is_cleaned` flag set on anonymized submissions

### Sensitive Data Handling
- Form variables marked as `is_sensitive_data`
- Sensitive variables cleared during anonymization
- Authentication attributes (BSN, KvK) can be hashed after registration
- `maybe_hash_identifying_attributes` task runs after registration

### File Cleanup
- Temporary uploads cleaned after submission completion
- File attachments removed during data removal
- PDF reports optionally retained for audit

### GDPR Compliance
- Privacy policy acceptance tracked per submission
- Statement of truth acceptance tracked
- Data minimization through configurable retention
- Right to be forgotten supported via removal mechanism

## Already in Procest

- OpenRegister object deletion
- No automated data lifecycle management

## Not Yet in Procest

- **Configurable retention periods** -- No per-form data retention configuration
- **Automated data removal** -- No Celery-based expired data cleanup
- **Anonymization mode** -- No option to strip sensitive data while keeping submission record
- **Sensitive data marking** -- No per-variable sensitivity flag
- **BSN/KvK hashing** -- No post-registration identifier hashing
- **Privacy consent tracking** -- No per-submission privacy acceptance record
- **Statement of truth tracking** -- No truthfulness declaration per submission
