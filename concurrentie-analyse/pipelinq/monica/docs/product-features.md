# Monica - Product Features Documentation

**Source:** monicahq.com features pages, GitHub README, source code analysis
**Date:** 2026-03-14

## Feature Categories

### 1. Contact Management (Core)

The central feature — a "personal rolodex" for managing information about people.

**Contact Profile:**
- First name, last name, nickname, maiden name
- Avatar/photo (with Uploadcare integration)
- Custom pronouns
- Gender (customizable list)
- Birthday and other important dates
- Religion
- How you met this person

**Contact Information:**
- Phone numbers (multiple)
- Email addresses (multiple)
- Social media handles (WhatsApp, Telegram, etc.)
- Custom contact information types (configurable)

**Family & Relationships:**
- Significant others / partners
- Children
- Parents
- Custom relationship types (friend, colleague, etc.)
- Bidirectional relationship linking between contacts

**Pets:**
- Pet name and category
- Customizable pet categories

**Work Information:**
- Job title
- Company (with company management at vault level)

**Labels & Organization:**
- Labels/tags for organizing contacts
- Favorite contacts
- Contact archiving
- Contact moving between vaults
- Contact sorting options

### 2. Interaction Tracking

**Activities:**
- Log activities done with contacts
- Activity date, description, type
- Custom activity types (configurable)

**Phone Calls:**
- Log calls with contacts
- Call reasons (customizable)
- Call date and notes

**Notes:**
- Add private notes to contacts
- Notes are timestamped
- Notes can be favorited

### 3. Reminders

- Set reminders about important dates or events
- Automatic birthday reminders (from contact important dates)
- Custom date reminders
- Notification channels: Email and Telegram
- Scheduled delivery via cron/queue

### 4. Dashboard

- Recently consulted contacts (quick access avatars)
- Upcoming events and reminders (calendar view)
- Favorite notes
- Recent calls log
- Configurable default tab

### 5. Journal System

**Journal Entries:**
- Manual journal entries (private online journal)
- Automatic activity logs (activities with contacts appear automatically)
- Post templates (customizable structure)
- Tags for journal posts

**Mood Tracking:**
- Daily mood rating (emoji-based)
- Mood tracking parameters (customizable)
- Mood reports over time

**Slices of Life:**
- Group journal entries into "slices" (periods/chapters)
- Cover images for slices
- Photo attachments to posts

**Journal Metrics:**
- Custom life metrics tracked over time
- Metric values per journal entry

### 6. Goals & Streaks

- Set goals for contacts (e.g., "call mom every week")
- Track streaks (consecutive goal completion)
- Goal progress visualization

### 7. Gifts & Debts

**Gifts:**
- Gift ideas (wishlist)
- Gifts given
- Gift occasions (customizable: birthday, Christmas, etc.)
- Gift states (customizable: idea, purchased, given)

**Loans/Debts:**
- Track money owed to or by contacts
- Toggle loan status (repaid/outstanding)

### 8. Tasks

- Create tasks associated with contacts
- Vault-level task management
- Task completion tracking

### 9. Life Events

- Major life events timeline
- Life event categories (customizable)
- Life event types (customizable, with positions)
- Toggle visibility of life events

### 10. Groups

- Create groups of contacts
- Group types (customizable: family, team, etc.)
- Group type roles (e.g., "leader", "member")

### 11. Documents & Photos

- Upload documents to contacts
- Photo galleries per contact
- File management at vault level
- Storage tracking per account

### 12. Vault System

Multi-vault architecture for data isolation:
- Create multiple vaults (workspaces)
- Per-vault settings and labels
- Per-vault important date types
- Per-vault life event categories
- Per-vault mood tracking parameters
- Per-vault quick fact templates
- User access management per vault
- Template assignment per vault

### 13. Reports

- Address reports (by city, country)
- Important date summaries
- Mood tracking event reports

### 14. Calendar

- Calendar view of upcoming events
- Integration with reminders and important dates

### 15. Search

- Full-text search across contacts
- Most-consulted contacts quick access
- Powered by Laravel Scout (Meilisearch/Typesense/Algolia/database)

### 16. Settings & Personalization

**Account Settings:**
- User management (multi-user per account)
- Storage management
- Account cancellation

**Personalization:**
- Custom genders
- Custom pronouns
- Custom relationship types
- Custom address types
- Custom contact information types
- Custom call reasons
- Custom currencies
- Custom gift occasions and states
- Custom group types and roles
- Custom pet categories
- Custom religions
- Configurable modules (show/hide sections on contact pages)
- Configurable templates (page layout per contact type)
- Post templates for journal entries

**User Preferences:**
- Locale/language (27 languages)
- Timezone
- Date format
- Number format
- Distance format (km/miles)
- Map provider preference
- Name display order
- Help tooltips toggle

**Notification Channels:**
- Email notifications
- Telegram notifications (with webhook)
- Notification logs
- Test notification sending

### 17. Security & Auth

- Email/password authentication
- Two-factor authentication
- WebAuthn (FIDO2 hardware keys)
- Social login (OAuth via Socialite)
- API tokens (Laravel Sanctum)
- Invitation system for new users

### 18. Integration

- **CalDAV:** Sync contacts with external calendar apps
- **CardDAV:** Sync contacts with external contact apps
- **vCard:** Import/export contacts
- **REST API:** Basic API (users, vaults) with Sanctum auth
- **Uploadcare:** Cloud file/image upload service

### 19. Quick Facts

- Quick fact templates per vault
- Customizable quick facts per contact
- Toggle quick fact visibility
