# ArkCase Conceptual Model

**Source:** arkcase.com/arkcase-conceptual-model/

## Core Entities

### Case Files
Case files are distinct events tracked by an organization. They have:
- Start date and end date
- Identifying number
- Assigned users and groups
- Tasks, folders, and documents

**FOIA Example:** A case (also known as a "request") starts when a citizen requests information and ends after the request is resolved. Users are analysts and supervisors. Tasks include record searches, redaction of records, and billing. Documents include records to be delivered to the citizen.

**Law Enforcement Example:** A case starts when a crime is reported and ends after either a criminal prosecution or determination the crime cannot be solved. Users are detectives, investigators, evidence specialists. Tasks include leads and witness interview requests. Documents include investigation reports and interview transcripts.

### Complaints
Complaints are lightweight pre-case objects that allow work without full case compliance overhead:
- Similar to case files but without all compliance requirements
- Can be **converted** to a full case file if deemed substantial
- Can be **referred** to another organization
- Can be **closed** with no further action (false alarms, trivial matters)

### Document Repositories
Plain folder structures without case management overhead:
- No start/end dates
- No compliance requirements
- Used for procedure manuals, historical case files, personal documents
- Essentially a network shared drive equivalent

## Object Relationships

Based on the codebase plugin structure, ArkCase manages these entity types:

| Entity | Plugin | Description |
|---|---|---|
| Case File | acm-case-file-plugin | Core case tracking |
| Complaint | acm-complaint-plugin | Pre-case complaints |
| Consultation | acm-consultation-plugin | Inter-agency consultations |
| Task | acm-task-plugin | Work items within cases |
| Person | acm-person-plugin | People involved in cases |
| Document Repository | acm-document-repository-plugin | Plain document folders |
| Object Association | acm-object-association-plugin | Links between objects |
| Category | acm-category-plugin | Categorization system |
| Business Process | acm-business-process-plugin | Workflow definitions |
| Dashboard | acm-dashboard-plugin | User dashboards |
| Report | acm-report-plugin | Report definitions |
| Billing | acm-billing-plugin | Financial tracking |
| Audit | acm-audit-plugin | Audit trail |
| Analytics | acm-analytics-plugin | Analytics/metrics |
| Profile | acm-profile-plugin | User profiles |
| Admin | acm-admin-plugin | System administration |

## RBAC Model

ArkCase uses Role-Based Access Control with:
- **Roles** determine what content users can access and what actions they can take
- **Groups** aggregate users with similar access needs
- **Participants** on each object (case, complaint, etc.) control per-object access
- **Participant types** include "No Access" and "Group No Access"
- Access controls are embedded in Solr search results

## Configuration Model

ArkCase is highly configurable:
- **Modules** can be activated/deactivated
- **Workflows** are business-based, supporting background processes
- **Events** trigger next steps (e.g., new case -> automated task routing)
- **Templates** for correspondence and email
- **Lookups** for configurable dropdown values
- **Holiday Schedule** for date calculations
- **Extra Fields** (v25.03.00+) for dynamic custom metadata
