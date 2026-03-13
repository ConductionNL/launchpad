# File Uploads

## What Open Forms Does

### Upload Flow
1. User selects file in form `file` component
2. File uploaded via API, stored as temporary upload
3. On submission completion, temporary files "claimed" as `SubmissionFileAttachment`
4. During registration, files uploaded to external DMS (Documenten API, SharePoint, etc.)

### SubmissionFileAttachment Model
- Links to Submission and specific form component
- Stores: original filename, content type, file size
- File stored in Django private media storage
- Pre-registration hook can upload files before main registration

### Component Pre-Registration
- `execute_component_pre_registration` task handles per-component file upload
- Files uploaded to DMS before main registration runs
- Status tracking: `ComponentPreRegistrationStatuses` (not_used, in_progress, success, failed)
- Results stored in `SubmissionValueVariable.pre_registration_result`

### File Cleanup
- `cleanup_temporary_files_for` task removes unclaimed temporary uploads
- Data removal process cleans file attachments based on retention policy

### Virus Scanning
- Configurable ClamAV integration for upload scanning

## Already in Procest

- Nextcloud file storage (native to platform)
- File management per case via OpenRegister

## Not Yet in Procest

- **Temporary upload -> claim flow** -- No staged file upload pattern
- **Automatic DMS upload on registration** -- No automatic Documenten API upload of case attachments
- **Component-level pre-registration** -- No per-field file processing hooks
- **File upload status tracking** -- No per-component upload status tracking
- **Virus scanning** -- No ClamAV integration for uploads
