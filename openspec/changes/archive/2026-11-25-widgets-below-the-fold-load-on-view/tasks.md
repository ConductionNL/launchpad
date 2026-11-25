# Tasks: widgets-below-the-fold-load-on-view

- [x] 1. `LazyMount`: mount the slot when the element comes within one viewport height, once settled (2.5 s, then idle), on beforeprint, or at once when eager or without IntersectionObserver; stay mounted
- [x] 2. The widget wrapper mounts the renderer through it; eager in edit mode and in the `?export=1` render
- [x] 3. Unit tests: eager, no IntersectionObserver, intersect, idle fallback, beforeprint, stays mounted, wrapper eager in edit mode
- [x] 4. (e2e written in tests/e2e/widgets-below-the-fold.spec.ts, runs in nightly) Measure on a live instance: the start page's first paint asks only the widgets on screen (perf spec, before/after): measured live 25 Nov 2026 (sandbox Nextcloud 34, launchpad development): a dashboard with a files widget on screen and a copy at y = 4971 px sent one contents request in the first 1.5 s, the on-screen one's, and mounted no CnFilesWidget for the copy; `widgets-below-the-fold.spec.ts` green live (3/3)
