# Submission Lifecycle Flow

```
CITIZEN BROWSER                    OPEN FORMS BACKEND                    EXTERNAL SYSTEMS
===============                    ==================                    ================

1. FORM START
   GET /api/v2/forms/{slug}  ---->  Return form definition
                                    + auth options
                                    + prefill data (if authenticated)

2. AUTHENTICATION (optional)
   Click auth button         ---->  Redirect to IdP (DigiD/eHerkenning)
   IdP authenticates         <----  ...
   Return with SAML/OIDC    ---->  Store BSN/KvK in session
                                    Create AuthInfo record
                                    Trigger prefill plugins  ---------->  BRP / KvK / StUF-BG APIs
                                    Receive prefill data     <----------
                                    Return pre-filled form data

3. FORM FILLING (per step)
   Fill fields, change values ---> Logic evaluation endpoint
                                    Evaluate JSON Logic triggers
                                    Execute actions:
                                     - Change field properties
                                     - Calculate values
                                     - Evaluate DMN ---------------------> Camunda DMN Engine
                                     - Fetch from service ---------------> External APIs
                                    Return mutations      <-----------
   Apply mutations to UI     <----

   Submit step               ---->  Validate all component values
                                    Run validation plugins
                                    Save SubmissionStep data
                                    Save SubmissionValueVariables
   Step confirmed            <----

4. OVERVIEW & CONFIRMATION
   Show overview page        <----  (unless skip_overview)
   Accept privacy policy
   Accept statement of truth
   Click Submit              ---->  Mark submission as completed
                                    Calculate price (if payment)
                                    Set completed_on timestamp
                                    transaction.on_commit:
                                      Schedule on_post_submission_event()

5. POST-COMPLETION CHAIN (Celery)
                                    [Task 1] maybe_register_appointment -> Appointment backend (JCC/Qmatic)
                                    [Task 2] pre_registration
                                      - Validate plugin options
                                      - Verify initial_data ownership ---> Objects API (if updating)
                                      - pre_register_submission ---------> ZGW: Create Zaak
                                      - Set public_registration_reference   Objects API: (prepare)
                                    [Task 3] component_pre_registration
                                      - Upload files to DMS ------------> Documenten API
                                    [Task 4] process_component_pre_reg
                                      - Check for failures, set retry flag
                                    [Task 5] generate_submission_report
                                      - Render HTML -> PDF
                                    [Task 6] register_submission
                                      - Call plugin.register_submission -> ZGW: Create Rol, Status,
                                                                            Upload documents,
                                                                            Set Eigenschappen
                                                                           Objects API: Create/Update object
                                                                           StUF-ZDS: SOAP calls
                                                                           Email: Send submission data
                                    [Task 7] update_payment_status -----> (if payment required)
                                    [Task 8] finalise_completion
                                      - Schedule confirmation email
                                      - Hash identifying attributes
                                      - Set retry flag if failed

6. CONFIRMATION PAGE
   Poll processing status    ---->  Check Celery task states
                                    Return: in_progress | done(success|failed)
   Show confirmation         <----  Display public_registration_reference
                                    + confirmation template content

7. PAYMENT (if required)
   Redirect to payment       ---->  Create SubmissionPayment
                                    plugin.start_payment() --------------> Ogone/Worldline
   Pay at provider           ----->
   Webhook callback          <----  handle_webhook() updates status
   Return redirect           ---->  handle_return()
                                    Trigger on_payment_complete event
                                    Re-run registration chain (Task 6-8)

8. CO-SIGNING (if required)
   (Original submitter done)
                                    Send cosign request email -----------> Co-signer's inbox
   Co-signer opens link      ---->  Authenticate (DigiD/eHerkenning)
   Co-signer approves        ---->  Verify OTP
                                    Set cosign_complete = True
                                    Trigger on_cosign_complete event
                                    Re-run registration chain (Task 2-8)

9. RETRY (Celery Beat)
                                    retry_processing_submissions
                                    Find submissions with needs_on_completion_retry
                                    For each: on_post_submission_event(on_retry)
                                    (Same chain as step 5, idempotent)

10. DATA REMOVAL (Celery Beat)
                                    Find expired submissions
                                    delete_permanently OR make_anonymous
                                    Clean file attachments
                                    Set _is_cleaned flag
```

## State Machine

```
Submission States:
  CREATED -----> FILLING (steps being completed)
    |                |
    v                v
  SUSPENDED     COMPLETED -----> PROCESSING (Celery chain)
    |                                |
    v                                +-----> REGISTERED (success)
  RESUMED                           |
    |                                +-----> FAILED (retry scheduled)
    v                                |
  COMPLETED                          +-----> COSIGN_WAITING
                                     |         |
                                     |         v
                                     |       COSIGNED -> REGISTERED
                                     |
                                     +-----> PAYMENT_WAITING
                                               |
                                               v
                                             PAID -> REGISTERED

Registration Statuses:
  pending -> in_progress -> success
                         -> failed (-> retry -> in_progress -> ...)
```
