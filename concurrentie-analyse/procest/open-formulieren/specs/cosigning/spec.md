# Co-signing

## What Open Forms Does

Co-signing is a workflow where a second person must authenticate and approve a submission before it is registered.

### Versions

**V1 (in-form, deprecated):**
- Co-sign component directly in the form
- Second actor authenticates within the same form session
- Submission is cosigned at completion time
- Problem: DigiD session caching can log in wrong person

**V2 (out-of-band, current):**
- Form contains a cosign component with email field
- Submitter provides co-signer's email address
- On completion, submission is NOT yet cosigned
- System sends email to co-signer with a link
- Co-signer opens link, authenticates (DigiD/eHerkenning), reviews and approves
- OTP verification for additional security (`CosignOTP` model)
- On co-sign complete, `on_cosign_complete` event triggers the registration chain

### State Tracking
- `cosign_complete` -- whether co-signing is done
- `cosign_request_email_sent` -- email to co-signer sent
- `cosign_confirmation_email_sent` -- confirmation after co-sign
- `cosign_privacy_policy_accepted` -- co-signer accepted privacy policy
- `cosign_statement_of_truth_accepted` -- co-signer confirmed truthfulness

### Co-sign Data
- `CosignV1Data`: version, plugin identifier, auth attribute (e.g., BSN), representation
- `CosignV2Data`: version, plugin identifier, cosigner BSN/KvK
- Stored in `Submission.co_sign_data` JSONField

### Impact on Registration
- Registration is blocked while `cosign_state.is_waiting`
- When cosigned, `on_cosign_complete` event re-triggers the full registration chain
- Co-signer identity included in registration data (e.g., as additional Rol on Zaak)

## Already in Procest

- None

## Not Yet in Procest

- **Co-sign workflow** -- No second-actor approval before registration
- **Email-based co-sign request** -- No out-of-band approval links
- **OTP verification for co-sign** -- No additional security layer
- **Registration blocking until co-sign** -- No approval gating of case creation
- **Co-signer authentication** -- No DigiD/eHerkenning auth for co-signers
- **Co-sign state machine** -- No tracking of co-sign progress per submission
