# Terugbel- en Taakbeheer Specification (Cross-App)

## Purpose

Terugbel- en taakbeheer (callback and task management) enables users across Conduction apps to create callback requests (terugbelverzoeken) and follow-up tasks when a question cannot be resolved immediately. Tasks are assigned to users or departments with priority and deadline, tracked through completion, and optionally trigger citizen notifications. **31% of klantinteractie-tenders** (16/52) explicitly require callback/task management, and task management is implicit in 83% of case management tenders.

This is a cross-app capability: Pipelinq creates KCC-originated callbacks and follow-up tasks, Procest creates case-related tasks (inspections, reviews, deadlines), and both share the underlying task data model via OpenRegister.

**Consuming apps**: Pipelinq (KCC callbacks, CRM follow-ups), Procest (case tasks, inspections, deadline tracking), OpenRegister (task storage)
**Tender frequency**: 16/52 terugbel (31%); 23% werkvoorraad/taakbeheer (16/69); 83% zaakgericht werken implies task management
**Standards**: VNG Klantinteracties (InterneTaak), Schema.org (Action, ScheduleAction), Common Ground

---

## Requirements

### Requirement 1: Create terugbelverzoek

KCC agents MUST be able to create callback requests during or after contacts.

#### Scenario 1.1: Create callback from active contact
- GIVEN an agent handling a phone contact for citizen "Jan de Vries" about case "Bouwvergunning #2024-001"
- WHEN the agent fills in subject, notes, assignee (department or person), priority, and deadline
- THEN a task MUST be created with type "terugbelverzoek" linked to client, contactmoment, and case
- AND it MUST appear in the assignee's inbox

#### Scenario 1.2: Assign to specific colleague
- GIVEN colleague "Petra Bakker" has prior context
- WHEN assigned to her with priority "Hoog"
- THEN the task MUST appear in Petra's personal inbox
- AND she MUST receive a notification with citizen name, phone, subject, and deadline

#### Scenario 1.3: Preferred callback time
- GIVEN citizen requests "dinsdag 14:00-16:00"
- THEN a `preferredTimeSlot` MUST be stored and prominently displayed

#### Scenario 1.4: Callback phone number override
- GIVEN the citizen calls from a different number
- THEN the callback number MUST be stored separately from the client's primary phone
- AND prominently displayed to the backoffice agent

#### Scenario 1.5: Required field validation
- GIVEN the agent tries to save without subject or assignee
- THEN inline validation errors MUST display
- AND the deadline MUST default to next business day 17:00

---

### Requirement 2: Create follow-up tasks

The system MUST support generic follow-up tasks across apps, not just callbacks.

#### Scenario 2.1: Information request task
- GIVEN an agent needs backoffice research
- THEN a task with type "informatievraag" MUST be creatable with full context (client, case, contactmoment)

#### Scenario 2.2: Task without client
- GIVEN an anonymous report (e.g., pothole)
- THEN task creation without client reference MUST be allowed

#### Scenario 2.3: Task from existing entity
- GIVEN the agent views a request or case
- WHEN clicking "Opvolgtaak aanmaken"
- THEN the form MUST pre-fill with entity title, client, and reference

#### Scenario 2.4: Case-related task (Procest)
- GIVEN a case handler in Procest needs an inspection scheduled
- WHEN they create a task with type "inspectie"
- THEN the task MUST link to the case and include location, inspection criteria, and assigned inspector

#### Scenario 2.5: Milestone-related task (Procest)
- GIVEN a case milestone "Hoorzitting" requires preparation
- WHEN the handler creates preparation tasks
- THEN tasks MUST be linked to both the case and the specific milestone

---

### Requirement 3: Task assignment and routing

Tasks MUST support assignment to individuals or groups/departments with re-assignment capability.

#### Scenario 3.1: Assign to department (Nextcloud group)
- GIVEN a task for "Afdeling Burgerzaken"
- THEN all group members MUST see the task in the shared inbox
- AND any member MUST be able to claim it (changing status to "in_behandeling")

#### Scenario 3.2: Reassign task
- GIVEN a claimed task where a colleague has better context
- THEN reassignment MUST update the assignee, notify the new assignee, and log the change

#### Scenario 3.3: Escalate overdue task
- GIVEN an unclaimed task approaching deadline (configurable threshold, e.g., 4 hours before)
- THEN an escalation notification MUST be sent to the group manager
- AND the task priority MUST be visually elevated
- AND the check MUST run via a Nextcloud background job (ITimedJob) every 15 minutes

#### Scenario 3.4: Assignment autocomplete
- GIVEN the agent types "Burg" in the assignment field
- THEN matching Nextcloud users and groups MUST display, visually distinguished

#### Scenario 3.5: Bulk reassignment
- GIVEN 5 tasks for an absent colleague
- THEN a manager MUST be able to reassign all at once with a single notification to the new assignee

---

### Requirement 4: Task status tracking

Tasks MUST be tracked through their full lifecycle.

#### Scenario 4.1: Complete a callback
- GIVEN the backoffice agent called back successfully
- WHEN marking as "Afgerond" with result text
- THEN completion timestamp and result MUST be stored
- AND the originating KCC agent MUST be notified

#### Scenario 4.2: Task expires past deadline
- GIVEN a task still "open" past deadline
- THEN status MUST change to "verlopen" via background job
- AND escalation notification MUST be sent to manager and originating agent

#### Scenario 4.3: Reopen completed task
- GIVEN a citizen calls back saying they were not contacted
- THEN the task MUST be reopenable with new deadline and history record

#### Scenario 4.4: Log unsuccessful callback attempt
- GIVEN the citizen does not answer
- THEN the attempt MUST be logged (timestamp + "Niet bereikbaar") with task remaining "in_behandeling"
- AND after 3 unsuccessful attempts, the system MUST suggest closing with "Burger niet bereikt"

#### Scenario 4.5: View task status history
- GIVEN a task with multiple status changes
- THEN a chronological history MUST display: each transition with timestamp, actor, and reason

---

### Requirement 5: Priority and deadline management

Priority levels and deadlines MUST be supported with visual indicators and sorting.

#### Scenario 5.1: High-priority visual distinction
- GIVEN a "Hoog" priority task due today
- THEN it MUST display at the top with red priority badge and urgency indication

#### Scenario 5.2: Sort by deadline
- GIVEN 10 tasks with various deadlines
- THEN sorting by deadline ascending MUST be supported
- AND overdue tasks MUST appear at top regardless of sort

#### Scenario 5.3: Priority escalation on approaching deadline
- GIVEN a "Normaal" task with deadline in 2 hours
- THEN visual priority MUST auto-elevate to "Hoog" display (original priority preserved in data)
- AND assignee MUST receive a reminder

#### Scenario 5.4: Business hours deadline calculation
- GIVEN a task created Friday 16:00 with 24-hour deadline
- THEN the deadline MUST be Monday 16:00 (skipping weekend)
- AND configurable business hours (default Mon-Fri 08:00-17:00) MUST be respected

---

### Requirement 6: Citizen status notification

The system SHOULD support notifying citizens about callback status.

#### Scenario 6.1: Callback scheduled notification
- GIVEN a callback is created
- THEN the citizen SHOULD receive a notification confirming the callback with reference number and expected window
- AND NO internal details (agent name, department) MUST be included

#### Scenario 6.2: Callback attempted notification
- GIVEN an unsuccessful callback attempt
- THEN the citizen SHOULD receive a notification that an attempt was made with instructions to reach the municipality

#### Scenario 6.3: Callback completed notification
- GIVEN a successful callback
- THEN the citizen SHOULD receive a satisfaction survey link (if configured)

---

### Requirement 7: Integration with personal work inbox

Tasks MUST integrate with the personal work inbox across apps.

#### Scenario 7.1: Task in my-work inbox
- GIVEN a terugbelverzoek assigned to "Petra Bakker"
- THEN it MUST appear in her personal inbox alongside leads, requests, and case tasks
- AND MUST be identifiable by type badge

#### Scenario 7.2: Filter by task type
- GIVEN 5 callbacks, 3 lead follow-ups, 2 case tasks
- THEN filtering by type "Terugbelverzoek" MUST show only the 5 callbacks

#### Scenario 7.3: Cross-app task count
- GIVEN tasks from both Pipelinq and Procest
- THEN the inbox MUST show combined counts with app source indicated
- AND overdue grouping MUST include tasks from all apps

---

### Requirement 8: Task search and filtering

Managers MUST be able to search and filter tasks across the organization.

#### Scenario 8.1: Search by citizen name
- GIVEN 50 open tasks
- WHEN searching "de Vries"
- THEN all tasks linked to matching clients MUST display

#### Scenario 8.2: Filter by department
- GIVEN tasks across departments
- THEN filtering by Nextcloud group MUST show only that department's tasks with status counts

#### Scenario 8.3: Task dashboard for managers
- GIVEN a manager overseeing 3 departments
- THEN: total open, overdue count, average completion time, and per-department breakdown MUST display

---

### Requirement 9: Task templates

Predefined templates SHOULD be available for common callback scenarios.

#### Scenario 9.1: Use template
- GIVEN template "Terugbellen vergunningsstatus" with preset subject, priority, assignee group, and deadline
- WHEN selected during creation
- THEN the form MUST pre-fill (all fields overridable)

#### Scenario 9.2: Manage templates
- GIVEN admin access
- THEN templates MUST be creatable, editable, and deletable

#### Scenario 9.3: Template usage statistics
- GIVEN 5 templates
- THEN usage count per template over 30 days MUST be displayed
- AND rarely used templates MUST be flagged

---

### Requirement 10: Case-specific task management (Procest)

Procest MUST support case-specific task types beyond callbacks.

#### Scenario 10.1: Inspection task
- GIVEN a VTH case requiring site inspection
- THEN an "Inspectie" task MUST be creatable with: location, inspection checklist, assigned inspector, required date
- AND the task MUST link to the case and appear on the case timeline

#### Scenario 10.2: Review/approval task
- GIVEN a case requiring management approval
- THEN a "Beoordeling" task MUST be creatable assigned to the approving authority
- AND the task MUST include: case summary, requested action, decision options (goedkeuren/afkeuren/aanpassen)

#### Scenario 10.3: Deadline-driven task auto-creation
- GIVEN a case type with configured process steps and deadlines
- THEN tasks MUST be automatically created for upcoming process milestones
- AND the tasks MUST reference the legal basis for the deadline (wettelijke termijn)

---

### Requirement 11: Task reporting

Task completion metrics MUST be available for performance management.

#### Scenario 11.1: Callback completion rate
- GIVEN a reporting period
- THEN the report MUST show: total callbacks, completed within SLA, overdue, average response time

#### Scenario 11.2: Department performance
- GIVEN multiple departments
- THEN per-department: task volume, average completion time, overdue rate MUST be displayed

#### Scenario 11.3: Agent workload report
- GIVEN per-agent task data
- THEN: open tasks, completed this week, average completion time, overdue tasks MUST be available
- AND agents with high overdue rates MUST be flagged

---

## Data Model

### Taak Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `type` | string | YES | terugbelverzoek, opvolgtaak, informatievraag, inspectie, beoordeling |
| `subject` | string | YES | Task subject |
| `description` | string | no | Detailed description |
| `client` | string (uuid) | no | Client reference |
| `zaak` | string (uuid) | no | Case reference |
| `contactmoment` | string (uuid) | no | Originating contactmoment |
| `request` | string (uuid) | no | Linked request |
| `assignee` | string | YES | User UID or group ID |
| `assigneeType` | string | YES | "user" or "group" |
| `priority` | string | YES | hoog, normaal, laag |
| `deadline` | datetime | YES | Completion deadline |
| `status` | string | YES | open, in_behandeling, afgerond, verlopen |
| `preferredTimeSlot` | string | no | Preferred callback window |
| `callbackPhone` | string | no | Override callback number |
| `result` | string | no | Completion result text |
| `completedAt` | datetime | no | Completion timestamp |
| `createdBy` | string | YES | Creating agent's UID |
| `attempts` | integer | no | Callback attempt counter |
| `sourceApp` | string | YES | Originating app |

---

## Dependencies

- OpenRegister (task storage and query API)
- Pipelinq (KCC callback creation, my-work inbox)
- Procest (case task creation, case linking)
- Nextcloud Groups API (department routing)
- Nextcloud Notification API (assignment, escalation)
- Nextcloud Background Jobs (ITimedJob for deadline monitoring)
- Activity Timeline spec (task events in entity timelines)

## Standards & References

- VNG Klantinteracties `InterneTaak` -- internal task entity
- Schema.org `Action`, `ScheduleAction` -- task modeling
- Common Ground -- task management in KCC workflows
- MijnOverheid -- citizen status notification channel (V1)
- WCAG AA -- task management UI accessibility
