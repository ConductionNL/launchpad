# Payment Flow

```
CITIZEN BROWSER              OPEN FORMS                    PAYMENT PROVIDER (Ogone/Worldline)
===============              ==========                    ==================================

1. FORM COMPLETION
   Submit form          ---> Check form.payment_backend
                              Calculate price:
                                - From Product.price OR
                                - From price_variable_key (logic-calculated)
                              Store price on Submission
                              Start post-completion chain
                              (pre-registration creates case/reference)

2. PAYMENT INITIATION
   Confirmation page    <--- Show: "Payment required: EUR X.XX"
                              Display payment button

   Click "Pay"          ---> POST /api/v2/submissions/{uuid}/payment/start
                              Create SubmissionPayment:
                                plugin_id, amount, status=started
                              Generate public_order_id:
                                template: "{year}-{public_reference}-{uid}"
                              Call plugin.start_payment():
                                Build payment URL with:
                                  - Order ID
                                  - Amount
                                  - Currency
                                  - Return URL
                                  - [Ogone: SHA-256 signature]
                              Return PaymentInfo:
                                type: GET or POST
                                url: provider URL
                                data: form fields (if POST)

3. REDIRECT TO PROVIDER
   Redirect/POST to     --->
   payment provider                                        Receive payment request
                                                           Display payment page
   Select payment method                                   (iDEAL, credit card, etc.)
   Complete payment                                        Process payment

4. RETURN FROM PROVIDER
   Redirect back         <--                               Redirect to return_url

   GET /payment/return   ---> plugin.handle_return():
     ?uuid={payment_uuid}      Verify payment parameters
     &status=...               Map provider status to
                                PaymentStatus enum
                              Redirect to confirmation page

5. WEBHOOK (async)
                                                           POST /payment/webhook
                              <--------------------------- {order_id, status,
                                                            provider_payment_id, ...}
                              plugin.handle_webhook():
                                Verify signature/headers
                                Find SubmissionPayment
                                Update status -> completed
                                Set provider_payment_id

                              Trigger on_payment_complete:
                                on_post_submission_event(
                                  submission_id,
                                  on_payment_complete)

6. RE-RUN REGISTRATION
                              Registration chain re-runs:
                                register_submission():
                                  (already registered -> skip if success)
                                update_submission_payment_status():
                                  Call plugin.update_payment_status()
                                  -> ZGW: PATCH zaak betalingsindicatie
                                  -> Objects API: Update payment record
                                payments.mark_registered():
                                  status: completed -> registered

7. CONFIRMATION
   Poll status           ---> Return processing result
   Show final            <--- Display: reference + payment confirmed
   confirmation


PAYMENT STATUS STATE MACHINE:

  started --> processing --> completed --> registered
                         --> failed
```

## Price Resolution Logic

```
1. Check Form.price_variable_key
   - If set, look up variable value from submission data
   - Variable value typically calculated by logic rules
     (e.g., "if option A selected: 50.00, if B: 75.00")

2. If no price_variable_key, check Form.product
   - Product.price is the static price

3. If neither, payment_required = False

4. Price stored as Decimal(10,2) on Submission.price
   at completion time
```

## Configuration

```
GlobalConfiguration:
  - wait_for_payment_to_register: bool
    True  = registration waits for payment before executing
    False = registration runs immediately, payment status updated later

  - payment_order_id_template: str
    Default: "{year}-{public_reference}-{uid}"
    Must be unique across all payments

Form:
  - payment_backend: plugin ID (e.g., "ogone", "worldline")
  - payment_backend_options: JSON (provider-specific config)
  - price_variable_key: str (form variable key for dynamic price)
  - product: FK to Product (static price)
```
