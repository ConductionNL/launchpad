# Payment Processing

## What Open Forms Does

### Payment Model
- `SubmissionPayment` model tracks individual payment transactions
- Fields: `uuid`, `submission` FK, `plugin_id`, `plugin_options`, `amount` (Decimal), `status`, `public_order_id`, `provider_payment_id`
- `PaymentStatus` enum: `started`, `processing`, `failed`, `completed`, `registered`
- `public_order_id` generated from template: `{year}-{public_reference}-{uid}`

### Payment Flow
1. Form has `payment_backend` configured (e.g., Ogone)
2. After submission, if `payment_required`, user is redirected to payment
3. `start_payment()` creates `SubmissionPayment`, returns `PaymentInfo` (URL + form data for redirect)
4. User pays at external provider
5. Provider calls webhook -> `handle_webhook()` updates payment status
6. User returns -> `handle_return()` redirects to confirmation
7. On `on_payment_complete` event, registration chain re-runs to update backend

### Payment Plugins

| Plugin | Provider | Protocol |
|--------|----------|----------|
| `ogone` | Ingenico/Ogone | SHA-256 signed form POST |
| `worldline` | Worldline (successor to Ogone) | REST API |
| `demo` | Demo | No actual payment |

### Price Calculation
- Static price from linked `Product`
- Dynamic price via `price_variable_key` -- a form variable calculated by logic rules
- `get_submission_price()` resolves the final price at completion time

### Payment + Registration Integration
- `GlobalConfiguration.wait_for_payment_to_register` controls whether registration waits for payment
- `update_payment_status` task updates the registration backend after payment
- `SubmissionPayment.mark_registered()` transitions completed payments to registered status

### Payment Order ID
- Template-based: configurable in GlobalConfiguration
- Must be unique, used as reference at payment provider

## Already in Procest

- None -- Procest has no payment processing

## Not Yet in Procest

- **Payment plugin system** -- No pluggable payment provider integration
- **Ogone/Worldline integration** -- No iDEAL/credit card payment flow
- **Price from form logic** -- No dynamic price calculation based on form answers
- **Payment-gated registration** -- No option to delay case registration until payment received
- **Payment status webhooks** -- No inbound payment provider callbacks
- **Payment order ID generation** -- No template-based payment reference system
- **Payment status tracking per submission** -- No per-submission payment state machine
