# ArkCase Frontend Documentation

**Source:** arkcase.com/frontend-documentation/

## Frontend Architecture

### Technology Stack
- **AngularJS** (1.x) -- the primary frontend framework
- HTML, CSS, SCSS
- JavaScript (27% of codebase)
- Standard Maven build with npm/yarn for frontend assembly

### UI Assembly
The user interface is assembled at deployment time from:
1. The main ArkCase user interface webapp
2. Any user interface extension JAR files
3. Assembly done via npm (Node Package Manager)

This allows the UI to be customized by developers and partners without modifying the core ArkCase services webapp or the ArkCase user interface webapp.

## Documentation Generation

ArkCase uses **ngdoc** (extension of jsdoc) for automated frontend documentation.

### Doc Server
- Repository: https://github.com/sergeik/doc-server
- Builds documentation for directives and services
- Controllers can also be documented (internal documentation)

### Documentation Standards

#### Controllers
```javascript
/**
 * @ngdoc function
 * @name controllerFunc
 * @description Controller's function description
 * @param {String} param Passed parameter
 * @returns {Number} Result of function work
 */
function controllerFunc(param) { ... }
```

#### Directives
```javascript
/**
 * @ngdoc directive
 * @name global.directive:treeView
 * @restrict E
 * @description The treeView directive renders simple FancyTree based Tree View
 * @param {expression} treeData Data structure used for tree rendering
 * @param {expression} onSelect Expression to evaluate upon tree item select
 */
angular.module('directives').directive('treeView', ['$q', ...
```

#### Services
```javascript
/**
 * @ngdoc service
 * @name upload-new-order.service:UploadNewOrder.NewOrderService
 * @description The NewOrderService used for single, spreadsheet and batch documents upload.
 */
angular.module('upload-new-order').factory('UploadNewOrder.NewOrderService', ['$http', ...
```

#### Methods
```javascript
/**
 * @ngdoc method
 * @name sendBulkOrderFilesToAlfresco
 * @methodOf upload-new-order.service:UploadNewOrder.NewOrderService
 * @description Uploads temp order files to ArkCase pipeline process
 * @param {Array} files Array of uploaded files objects
 * @returns {HttpPromise} Future info about uploaded files
 */
```

### Common Documentation Issues
1. Missing description (comment at top of file)
2. Incorrect "methodOf" property -- must use full controller/service name
3. Incorrect delimiters for parameter types -- use `{...}` not `(...)`
4. Failure to test ngdoc annotations with doc-server before commit

## Frontend Module Structure

Based on the UI source paths:
```
acm-user-interface/ark-web/src/main/webapp/resources/
  directives/    -- Reusable UI directives
  filters/       -- Data transformation filters
  modules/       -- Feature modules
  services/      -- Shared services
```

## Notable Frontend Characteristics

1. **AngularJS 1.x** -- this is a legacy framework (EOL since December 2021)
2. **FancyTree** -- used for tree views
3. **PDFTron** -- document viewer and redaction
4. **Node 6** required on macOS (extremely outdated)
5. **Not a modern SPA** -- traditional AngularJS architecture
6. **No mention of TypeScript** -- pure JavaScript

This is a significant competitive weakness. Modern case management platforms use React, Vue, or Angular (2+) with TypeScript.
