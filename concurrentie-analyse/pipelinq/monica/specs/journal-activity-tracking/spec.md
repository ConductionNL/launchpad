---
competitor: monica
analyzed_date: 2026-03-14
feature: journal-activity-tracking
category: tracking
---

# Journal and Activity Tracking

## Overview

Monica combines manual journaling with automatic activity logging into a unified timeline. This creates a comprehensive history of interactions and personal reflections.

## Journal Components

### Posts (Manual Entries)
- Free-form text entries
- Structured via post templates (customizable sections)
- Tags for categorization
- Photo attachments
- Metric values (custom life metrics per entry)
- Slices of Life grouping

### Activities (Automatic Entries)
- Activities logged on contacts appear automatically in the journal
- Shows activity description, date, participants
- Created when users log activities on contact pages

### Mood Tracking
- Daily mood rating (emoji-based scale)
- Customizable mood tracking parameters per vault
- Mood reports over time

### Slices of Life
- Group journal entries into chapters/periods
- Cover images for visual distinction
- Organizational tool for long-term journaling

### Life Metrics
- Custom numeric/text metrics tracked per journal entry
- Per-vault metric definitions
- Historical tracking for trend analysis

## Journal UI

- Timeline layout (chronological, newest first)
- Mixed content types in single feed (manual entries, automatic activities, mood ratings)
- Date-based navigation
- Per-vault journal (each vault has its own journal)

## Reports

- Mood tracking event reports (trends over time)
- Address reports (geographic distribution)
- Important date summaries

## Technical Implementation

- Vault domain: `app/Domains/Vault/ManageJournals/` (Services, Web)
- Models: Journal, Post, PostSection, PostMetric, PostTemplate, PostTemplateSection, SliceOfLife, Tag, JournalMetric
- Vue pages: Journal/, Journal/Metrics/, Journal/Photo/, Journal/Post/, Journal/Slices/

## Relevance to Pipelinq

The journal/activity tracking pattern is highly relevant to Pipelinq:
1. **Mixed timeline** — combining automatic system events with manual notes is excellent UX for pipeline stage history
2. **Templates for entries** — post templates could inspire structured stage transition notes
3. **Metrics tracking** — custom metrics per entry could apply to pipeline KPI tracking
4. **Mood tracking concept** — could translate to pipeline health indicators or satisfaction ratings
5. **Slices of Life** — could inspire pipeline phase/milestone grouping of activities
