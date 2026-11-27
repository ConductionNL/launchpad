# Tasks: more-menu-in-page-header

- [x] `src/utils/pageMenuTarget.js`: find the page header action row (store page, index page header buttons) and follow it as pages render and route
- [x] `App.vue`: teleport the page More menu into that row, last by flex order; fall back to the top end of the content without a row
- [x] Unit tests: `src/utils/__tests__/pageMenuTarget.spec.js`, `src/__tests__/App.pageMenuPlacement.spec.js`
