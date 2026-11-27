# Delta: dashboard-switcher

## REMOVED Requirements

### Requirement: REQ-SWITCH-001 Three-section navigation

**Reason**: Ruben's review of 27 Nov 2026, item 4: the dashboards sidebar goes; dashboards are switched in the More
menu only (boards LpMeer, LpMeerAlle; runtime-shell REQ-SHELL-014, REQ-SHELL-015).

**Migration**: none; the More menu lists the same dashboards.

### Requirement: REQ-SWITCH-002 Switch click semantics

**Reason**: the sidebar goes (item 4). Choosing a dashboard in the More menu opens it (REQ-SHELL-014).

**Migration**: none.

### Requirement: REQ-SWITCH-003 Active item highlight

**Reason**: the sidebar goes (item 4). The More menu checks the open dashboard (REQ-SHELL-014).

**Migration**: none.

### Requirement: REQ-SWITCH-004 Personal dashboard delete affordance

**Reason**: the sidebar goes (item 4). Delete in This dashboard deletes a personal dashboard (REQ-SHELL-013).

**Migration**: none.

### Requirement: REQ-SWITCH-005 Create-dashboard affordance

**Reason**: the sidebar goes (item 4); no board draws a create-empty-dashboard control. Duplicate as new dashboard and
the Marketplace stay.

**Migration**: none. Listed as an open question in the change.

### Requirement: REQ-SWITCH-006 Slide-in animation

**Reason**: the sidebar goes (item 4).

**Migration**: none.

### Requirement: REQ-SWITCH-007 Icon rendering via shared renderer

**Reason**: the sidebar goes (item 4). The More menu rows keep using the shared dashboard icon renderer.

**Migration**: none.

### Requirement: REQ-SWITCH-008 Dedicated Add-Dashboard card button

**Reason**: the sidebar goes (item 4); see REQ-SWITCH-005.

**Migration**: none.

### Requirement: REQ-SWITCH-009 Persistent sidebar footer with brand attribution and Documentation

**Reason**: item 7: Powered by sendent and Conduction move to the footer of the More menu (REQ-SHELL-017).
Documentation is in the Settings group of the More menu.

**Migration**: none.
