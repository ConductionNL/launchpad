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

**Reason**: the sidebar goes (item 4). Creating a new dashboard moves to "New dashboard" at the foot of the expanded
Dashboards list in the More menu (runtime-shell REQ-SHELL-015; Ruben, 27 Nov 2026).

**Migration**: none.

### Requirement: REQ-SWITCH-006 Slide-in animation

**Reason**: the sidebar goes (item 4).

**Migration**: none.

### Requirement: REQ-SWITCH-007 Icon rendering via shared renderer

**Reason**: the sidebar goes (item 4). The More menu rows keep using the shared dashboard icon renderer.

**Migration**: none.

### Requirement: REQ-SWITCH-008 Dedicated Add-Dashboard card button

**Reason**: the sidebar goes (item 4); New dashboard in the More menu replaces it (REQ-SHELL-015).

**Migration**: none.

### Requirement: REQ-SWITCH-009 Persistent sidebar footer with brand attribution and Documentation

**Reason**: item 7: Powered by sendent and Conduction move to the footer of the More menu (REQ-SHELL-017).
Documentation is in the Settings group of the More menu.

**Migration**: none.
