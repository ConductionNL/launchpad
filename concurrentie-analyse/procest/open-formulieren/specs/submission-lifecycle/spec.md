# Submission Lifecycle

## What Open Forms Does

### Submission Model
The `Submission` model is the central entity tracking a citizen's form completion:

- **State fields**: `created_on`, `completed_on`, `suspended_on`, `language_code`
- **Registration tracking**: `registration_status` (pending/in_progress/success/failed), `registration_attempts`, `last_register_date`, `registration_result` (JSON), `public_registration_reference`, `pre_registration_completed`
- **Payment state**: `price` (Decimal), links to `SubmissionPayment` records
- **Co-sign state**: `co_sign_data`, `cosign_complete`, `cosign_request_email_sent`, `cosign_confirmation_email_sent`
- **Email state**: `confirmation_email_sent`, `payment_complete_confirmation_email_sent`
- **Privacy**: `privacy_policy_accepted`, `statement_of_truth_accepted` (both for submitter and co-signer)
- **Data lifecycle**: `_is_cleaned` (sensitive data removed), `initial_data_reference` (for updating existing objects)
- **Retry**: `needs_on_completion_retry`, `on_completion_task_ids` (Celery task IDs)

### Submission Steps
- `SubmissionStep` links Submission to FormStep, stores per-step `data` (JSONField) and `completed` flag
- `SubmissionValueVariable` stores individual variable values with source tracking (user_input, prefill, logic, dmn, sensitive_data_cleaner)
- `SubmissionFileAttachment` handles uploaded files per submission

### Submission Report
- `SubmissionReport` stores the generated PDF confirmation report
- PDF generated as a Celery task after registration

### Post-Completion Chain (Celery)
The `on_post_submission_event()` function orchestrates a Celery chain of 8 tasks:

1. **`maybe_register_appointment`** -- If form is appointment-type, register with appointment backend
2. **`pre_registration`** -- Validate plugin options, verify initial data ownership, call `pre_register_submission()` (e.g., create Zaak in ZGW), set public reference number
3. **`execute_component_pre_registration_group`** -- Fan-out component-level pre-registration hooks (e.g., file upload to DMS)
4. **`process_component_pre_registration`** -- Check if any component pre-registration failed; set retry flag
5. **`generate_submission_report`** -- Create PDF confirmation report
6. **`register_submission`** -- Call the registration backend's `register_submission()` (e.g., attach documents to Zaak)
7. **`update_submission_payment_status`** -- If payment required and completed, update registration backend
8. **`finalise_completion`** -- Schedule confirmation emails, hash identifying attributes if configured

### Retry Mechanism
- `retry_processing_submissions` Celery beat task picks up submissions with `needs_on_completion_retry=True`
- Configurable `registration_attempt_limit` in GlobalConfiguration
- Submissions older than `RETRY_SUBMISSIONS_TIME_LIMIT` hours are abandoned
- Uses `celery-once` (`QueueOnce`) to prevent duplicate registration attempts

### Lifecycle Stages
- `Stages`: successfully_completed, incomplete, errored, other
- `ProcessingStatuses`: in_progress, done
- `ProcessingResults`: failed (return to form start), success (show confirmation page)

### Post-Submission Events
- `on_completion` -- Normal form submission
- `on_payment_complete` -- Payment webhook received
- `on_cosign_complete` -- Co-signer completed their flow
- `on_retry` -- Celery beat retry

### Data Removal
- Two modes: `delete_permanently` or `make_anonymous` (remove sensitive data only)
- Configurable retention period per form
- `_is_cleaned` flag tracks whether sensitive data has been scrubbed

## Already in Procest

- Basic case/submission concept (OpenRegister objects represent cases)
- Status tracking on cases (via pipeline stages in Pipelinq)

## Not Yet in Procest

- **Celery task chain for post-completion** -- Procest has no orchestrated async task chain after form submission
- **Pre-registration / registration split** -- No two-phase registration (create case, then attach documents)
- **Automatic retry with attempt limits** -- No built-in retry mechanism for failed backend registrations
- **Submission suspension and resumption** -- Citizens cannot save and resume a partially completed form
- **Co-signing workflow** -- No second-actor approval flow
- **PDF report generation** -- No automatic PDF summary of submitted data
- **Public registration reference** -- No user-facing case number generated at submission time
- **Submission value variable tracking** -- No per-variable source tracking (user input vs prefill vs logic)
- **Data removal / anonymization** -- No configurable data retention or sensitive data scrubbing
- **Payment integration in submission flow** -- No payment gating of registration
- **Processing status polling** -- No real-time status endpoint for frontend to poll task chain progress
