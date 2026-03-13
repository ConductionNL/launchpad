---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Multi-Contactmoment (Concurrent Interaction Handling) - KISS

## Purpose
KISS supports handling multiple concurrent contactmomenten (customer interactions). A KCM can put one call on hold, start handling a new walk-in customer, and switch back — each interaction maintains its own isolated state. This is critical for call center environments where interruptions are common.

## Architecture Overview
- **Frontend**: Pinia session-scoped stores. Each contactmoment gets its own Pinia session with isolated state via `useContactmomentStore(sessionId)`
- **BFF**: Stateless — each contactmoment is persisted independently via API calls
- **State isolation**: Vue Router + session IDs ensure that navigating between contactmomenten does not leak state
- **UI**: Tab-like interface showing active contactmomenten with visual indicators

## Data Model

### Session Management
```typescript
// Each contactmoment has a unique session
interface ContactmomentSession {
    sessionId: string;           // UUID, created on start
    startdatum: string;          // When the interaction began
    klant?: KlantIdentificatie;  // Identified customer (if any)
    vragen: Vraag[];             // Questions within this interaction
    huidigeVraag: Vraag;         // Currently active question
    kanaal?: string;             // Communication channel
    status: "actief" | "gepauzeerd"; // Active or paused
}
```

### Active Contactmomenten (Frontend State)
```typescript
// The store tracks all active sessions
interface ContactmomentenState {
    sessions: Map<string, ContactmomentSession>;
    activeSessionId: string | null;
}
```

## Business Logic

### Starting a New Contactmoment
1. KCM clicks "Nieuw contactmoment" (new contact moment)
2. System creates a new session with a unique ID
3. Frontend creates a new Pinia store instance scoped to this session
4. The contactmoment tab appears in the UI
5. KCM can immediately start searching, identifying clients, etc.

### Switching Between Contactmomenten
1. KCM clicks on a different contactmoment tab
2. Frontend switches the active session ID
3. All components re-render with the selected session's state
4. The paused contactmoment retains all its state (search results, notes, linked cases)
5. No data is lost during switching

### Closing a Contactmoment
1. KCM navigates to the finalization screen
2. System validates required fields (channel, at least one question, etc.)
3. Contactmoment is saved to OpenKlant API
4. Extended details saved to BFF PostgreSQL
5. Session is removed from active sessions
6. Tab disappears from the UI

### State Isolation Guarantees
- Each session has its own Pinia store instance — no shared mutable state
- Route parameters include the session ID to prevent URL-based state confusion
- API calls are scoped to the active session to prevent cross-contamination
- Browser refresh preserves active sessions (state is persisted)

### Multi-Question Within a Session
Within a single contactmoment, the KCM can handle multiple "vragen" (questions). Each question can have its own:
- Knowledge articles consulted
- Cases linked
- Contact request created
- Conversation result
- Notes

This allows a citizen to ask "I want to know about my building permit AND my parking fine" in one call, with each topic tracked separately.

## Requirements (as observed)
- Must support at least 2-3 concurrent contactmomenten
- Must provide visual switching UI (tabs)
- Must guarantee complete state isolation between sessions
- Must preserve state during switches (no data loss)
- Must support browser refresh without losing active sessions
- Must allow independent finalization of each contactmoment

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Concurrent interactions | Yes (multi-session) | Single active interaction |
| State isolation | Yes (Pinia sessions) | N/A |
| Tab-based switching | Yes | No |
| Multi-question per interaction | Yes | Single thread |
| Browser persistence | Yes | N/A |
| Pause/resume | Yes (implicit via tabs) | N/A |

**Gap for Pipelinq**: Multi-session support is a call-center-specific need. Pipelinq could benefit from a simpler "recent interactions" sidebar that lets agents quickly switch context, though full session isolation is likely overengineering for Pipelinq's use case.
