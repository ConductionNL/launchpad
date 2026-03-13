# Appointment Scheduling

## What Open Forms Does

Forms can be configured as appointment forms (`is_appointment=True`), which replaces the normal form flow with an appointment booking workflow.

### Appointment Model
- `AppointmentInfo` linked to Submission
- Stores: product, location, datetime, customer details, status
- Appointment backends: JCC, JCC REST, Qmatic, Demo

### Appointment Flow
1. Form marked as `is_appointment`
2. Citizen selects product(s), location, date/time
3. Provides contact details
4. On submission, `maybe_register_appointment` task creates appointment in backend
5. Confirmation email includes appointment details
6. Appointment can be cancelled/modified via token-protected URLs

### Appointment Plugin Interface
- `get_available_products()` -- List bookable products/services
- `get_locations(products)` -- Available locations for selected products
- `get_dates(products, location)` -- Available dates
- `get_times(products, location, day)` -- Available time slots
- `create_appointment(products, location, datetime, customer, remarks)` -- Book it
- `delete_appointment(identifier)` -- Cancel
- `get_appointment_details(identifier)` -- Retrieve details

### Customer Details
- Configurable required fields per appointment backend
- Phone, email, last name, birth date commonly required
- Normalizers for input sanitization

## Already in Procest

- None -- Procest has no appointment scheduling

## Not Yet in Procest

- **Appointment form mode** -- No appointment booking flow
- **Product/location/timeslot selection** -- No calendar-based booking UI
- **JCC/Qmatic integration** -- No connection to municipal appointment systems
- **Appointment cancellation/modification** -- No self-service appointment management
- **Token-protected appointment URLs** -- No secure link-based appointment access
