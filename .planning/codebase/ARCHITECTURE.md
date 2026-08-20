# Architecture

**Analysis Date:** 2026-08-20

## Pattern Overview

**Overall:** GLPI plugin with MVC-style entry scripts, GLPI database models, injectable type adapters, and a CSV backend pipeline.

**Key Characteristics:**
- GLPI owns application bootstrapping, routing, authentication, database connection, template rendering, and asset loading. This plugin contributes hooks in `setup.php`, install/migration logic in `hook.php`, page controllers in `front/`, AJAX callbacks in `ajax/`, domain classes in `inc/`, Twig templates in `templates/`, and static assets in `public/`.
- Import models are persisted configuration. `PluginDatainjectionModel` in `inc/model.class.php` stores what kind of GLPI item to import, import/update behavior, visibility, parsing options, mappings, and additional info requirements.
- Injectable types are adapter classes. Files such as `inc/computerinjection.class.php`, `inc/manufacturerinjection.class.php`, and `inc/groupinjection.class.php` extend GLPI item classes and implement `PluginDatainjectionInjectionInterface` from `inc/injectioninterface.class.php`.
- CSV is the only detected file backend. `PluginDatainjectionBackend::getInstance()` in `inc/backend.class.php` maps `csv` to `PluginDatainjectionBackendcsv` in `inc/backendcsv.class.php`.
- Long-running imports are split into browser-driven batches. `templates/clientinjection_injection.html.twig` loads `public/js/injection_progress.js`, which repeatedly posts to `ajax/inject_batch.php`; each request calls `PluginDatainjectionClientInjection::processBatch()` in `inc/clientinjection.class.php`.

## Layers

**GLPI Plugin Registration Layer:**
- Purpose: Declare plugin metadata, compatible GLPI versions, menu placement, rights/profile tab integration, assets, upload directory, and injectable type registry.
- Location: `setup.php`
- Contains: `plugin_init_datainjection()`, `plugin_version_datainjection()`, `getTypesToInject()`, `plugin_datainjection_migratetypes_datainjection()`, `plugin_datainjection_checkDirectories()`, and `plugin_datainjection_geturl()`.
- Depends on: GLPI globals/classes including `$PLUGIN_HOOKS`, `$CFG_GLPI`, `$INJECTABLE_TYPES`, `Plugin`, `Session`, `Profile`, and GLPI constants such as `GLPI_PLUGIN_DOC_DIR`.
- Used by: GLPI plugin loader, runtime URL generation, model/type dropdowns, and plugin hooks.

**Install and Migration Layer:**
- Purpose: Create/drop plugin tables, migrate old schemas, create initial rights, and clean uploaded plugin files on uninstall.
- Location: `hook.php`
- Contains: `plugin_datainjection_install()`, `plugin_datainjection_uninstall()`, and many versioned migration functions.
- Depends on: GLPI `$DB`, `Migration`, `DBConnection`, `PluginDatainjectionProfile`, `ProfileRight`, `Session`, and `Toolbox`.
- Used by: GLPI plugin install, update, and uninstall lifecycle.

**HTTP Page Controller Layer:**
- Purpose: Handle full-page GLPI screens and form submissions.
- Location: `front/`
- Contains:
  - `front/clientinjection.form.php` for selecting a model, uploading an import file, starting import processing, and finishing/canceling an import.
  - `front/model.php` for model search/list view.
  - `front/model.form.php` for model create/update/delete/validate/upload/sample actions.
  - `front/mapping.form.php` for saving column mappings.
  - `front/info.form.php` for saving additional information fields.
  - `front/popup.php` for preview/log popups.
  - `front/export.csv.php` and `front/export.pdf.php` for export actions.
- Depends on: GLPI `Session`, `Html`, `Toolbox`, `Search`, and domain classes under `inc/`.
- Used by: GLPI menu/config pages and browser navigation.

**AJAX Callback Layer:**
- Purpose: Return partial HTML or JSON for model selection, mapping dropdowns, import progress, and result rendering.
- Location: `ajax/`
- Contains:
  - `ajax/dropdownSelectModel.php` loads additional info/upload UI for the selected model.
  - `ajax/dropdownChooseField.php` and `ajax/dropdownMandatory.php` drive dependent mapping field controls.
  - `ajax/inject_batch.php` processes one batch and returns JSON progress.
  - `ajax/results.php` renders final result HTML.
  - `ajax/injection.php` renders the import step for a selected session model.
- Depends on: GLPI `Session`, `Html`, `Ajax`, domain classes under `inc/`, and browser AJAX calls.
- Used by: Twig templates and JavaScript in `templates/clientinjection.html.twig`, `templates/clientinjection_injection.html.twig`, and `public/js/injection_progress.js`.

**Model Configuration Layer:**
- Purpose: Store reusable import models, CSV-specific options, column mappings, and additional required information.
- Location: `inc/model.class.php`, `inc/modelcsv.class.php`, `inc/mapping.class.php`, `inc/mappingcollection.class.php`, `inc/info.class.php`, `inc/infocollection.class.php`
- Contains:
  - `PluginDatainjectionModel` for main model state and workflow steps.
  - `PluginDatainjectionModelcsv` for delimiter/header settings.
  - `PluginDatainjectionMapping` and `PluginDatainjectionMappingCollection` for CSV-column-to-GLPI-field mappings.
  - `PluginDatainjectionInfo` and `PluginDatainjectionInfoCollection` for additional non-file values requested at import time.
- Depends on: GLPI `CommonDBTM`, `CommonDBChild`, `$DB`, `Session`, `TemplateRenderer`, `Dropdown`, `Toolbox`, `Ajax`, and backend/type layers.
- Used by: Page controllers, AJAX callbacks, import engine, and templates.

**File Backend Layer:**
- Purpose: Validate, parse, count, read, encode, and delete uploaded import files.
- Location: `inc/backend.class.php`, `inc/backendcsv.class.php`, `inc/backendinterface.class.php`, `inc/data.class.php`
- Contains:
  - `PluginDatainjectionBackend` abstract base and backend factory.
  - `PluginDatainjectionBackendcsv` CSV implementation.
  - `PluginDatainjectionBackendInterface` backend contract.
  - `PluginDatainjectionData` in-memory read sample/data wrapper.
- Depends on: PHP file/CSV APIs, Safe function wrappers, GLPI `Toolbox`, and plugin constants.
- Used by: `PluginDatainjectionModel::readUploadedFile()` and `PluginDatainjectionClientInjection::showInjectionForm()`.

**Import Execution Layer:**
- Purpose: Convert one CSV row into GLPI item fields, validate mapped/mandatory/additional values, and add or update GLPI records.
- Location: `inc/engine.class.php`, `inc/commoninjectionlib.class.php`, and `inc/*injection.class.php`
- Contains:
  - `PluginDatainjectionEngine::injectLine()` to transform one CSV row into typed field arrays and call the selected injection adapter.
  - `PluginDatainjectionCommonInjectionLib` for common validation, formatting, DB presence checks, add/update behavior, result constants, and shared import mechanics.
  - Per-type injection classes such as `PluginDatainjectionComputerInjection`, `PluginDatainjectionGroupInjection`, and `PluginDatainjectionManufacturerInjection`.
- Depends on: Model/mapping/configuration layer, GLPI item/search APIs, GLPI DB models, and `PluginDatainjectionInjectionInterface`.
- Used by: `PluginDatainjectionClientInjection::processBatch()` in `inc/clientinjection.class.php`.

**Session and Progress Layer:**
- Purpose: Persist import state across page loads and batch AJAX requests while avoiding very large session arrays.
- Location: `inc/session.class.php`, `inc/clientinjection.class.php`, `public/js/injection_progress.js`, `templates/clientinjection_injection.html.twig`
- Contains:
  - `PluginDatainjectionSession` wrappers for reading/writing `$_SESSION['datainjection']`.
  - File-backed session payloads for `results`, `error_lines`, `injection_lines`, `injection_results`, and `injection_error_lines` under `GLPI_TMP_DIR`.
  - Batch offset/progress handling in `PluginDatainjectionClientInjection::processBatch()`.
  - Browser loop in `startBatchInjection()` in `public/js/injection_progress.js`.
- Depends on: GLPI sessions, `GLPI_TMP_DIR`, JSON encoding/decoding, AJAX callbacks, and import engine.
- Used by: Import workflow from upload through final result.

**Presentation Layer:**
- Purpose: Render GLPI-compatible forms, tabs, mapping screens, progress UI, results, and logs.
- Location: `templates/`, `public/js/`, `public/css/`, `pics/`
- Contains: Twig templates such as `templates/clientinjection.html.twig`, `templates/clientinjection_upload_file.html.twig`, `templates/clientinjection_injection.html.twig`, `templates/clientinjection_result.html.twig`, `templates/model_advanced_form.html.twig`, `templates/mappings_form.html.twig`, and `templates/log_results.html.twig`.
- Depends on: GLPI Twig macros, GLPI CSS/JS environment, jQuery, and template data provided by `inc/*.class.php`.
- Used by: `TemplateRenderer` calls in domain classes and GLPI asset hooks from `setup.php`.

## Data Flow

**Model Creation and Mapping Flow:**

1. User opens the model list/detail screens through `front/model.php` or `front/model.form.php`.
2. `PluginDatainjectionModel` in `inc/model.class.php` creates or updates `glpi_plugin_datainjection_models`.
3. CSV-specific settings are stored through `PluginDatainjectionModelcsv` in `inc/modelcsv.class.php`.
4. User uploads a sample CSV through `front/model.form.php`.
5. `PluginDatainjectionModel::processUploadedFile()` reads the sample with `PluginDatainjectionBackendcsv`, creates `PluginDatainjectionMapping` rows, and advances the model step to mapping.
6. User saves mappings through `front/mapping.form.php`, which updates `glpi_plugin_datainjection_mappings`.
7. User optionally configures additional information through `front/info.form.php` and `PluginDatainjectionInfo`.
8. User validates the model in `front/model.form.php`, calling `PluginDatainjectionModel::switchReadyToUse()`.

**CSV Import Flow:**

1. User opens `front/clientinjection.form.php`, which renders `PluginDatainjectionClientInjection::showForm()` using `templates/clientinjection.html.twig`.
2. Model selection triggers `ajax/dropdownSelectModel.php`, which shows upload/additional-info controls for the selected model.
3. Upload submission returns to `front/clientinjection.form.php`, which validates mandatory additional fields and calls `PluginDatainjectionModel::processUploadedFile()` in process mode.
4. The uploaded CSV is copied to `PLUGIN_DATAINJECTION_UPLOAD_DIR`, parsed by `PluginDatainjectionBackendcsv`, checked against saved mappings, and retained for processing.
5. `PluginDatainjectionClientInjection::showInjectionForm()` reads all import lines into file-backed session storage via `PluginDatainjectionSession::setParam('injection_lines', ...)`.
6. `templates/clientinjection_injection.html.twig` loads `public/js/injection_progress.js`, which posts batches to `ajax/inject_batch.php`.
7. `ajax/inject_batch.php` calls `PluginDatainjectionClientInjection::processBatch()`.
8. Each batch constructs `PluginDatainjectionEngine`, calls `injectLine()` for each CSV line, and appends results/errors through `PluginDatainjectionSession`.
9. `PluginDatainjectionEngine::injectLine()` maps CSV columns to GLPI fields and invokes the selected `PluginDatainjection*Injection::addOrUpdateObject()` adapter.
10. The adapter delegates common add/update behavior to `PluginDatainjectionCommonInjectionLib`.
11. When all lines finish, `processBatch()` deletes the uploaded CSV, moves result payloads to final session keys, and marks the import step as result.
12. Browser JS loads `ajax/results.php`, which calls `PluginDatainjectionClientInjection::showResultsForm()` and renders `templates/clientinjection_result.html.twig`.

**State Management:**
- Persistent model state is stored in GLPI database tables created in `hook.php`.
- Request/session workflow state is stored under `$_SESSION['datainjection']`.
- Large import arrays are stored as files under `GLPI_TMP_DIR` by `inc/session.class.php`.
- Uploaded CSV files are stored under `PLUGIN_DATAINJECTION_UPLOAD_DIR` until parsing/import completes.

## Key Abstractions

**PluginDatainjectionModel:**
- Purpose: Represents a reusable import model and its lifecycle steps.
- Examples: `inc/model.class.php`, `front/model.form.php`, `templates/model_advanced_form.html.twig`
- Pattern: GLPI `CommonDBTM` model with step constants, rights checks, database-backed fields, tabs, and helper methods for loading mappings, infos, CSV-specific settings, and uploaded files.

**Backend Interface and CSV Backend:**
- Purpose: Hide file parsing details behind a backend contract.
- Examples: `inc/backendinterface.class.php`, `inc/backend.class.php`, `inc/backendcsv.class.php`
- Pattern: Interface plus abstract factory. Add a backend by implementing `PluginDatainjectionBackendInterface`, creating `inc/backend{type}.class.php`, and extending `PluginDatainjectionBackend::getInstance()` in `inc/backend.class.php`.

**Injection Interface and Type Adapters:**
- Purpose: Define how each GLPI item type exposes importable fields and executes add/update.
- Examples: `inc/injectioninterface.class.php`, `inc/computerinjection.class.php`, `inc/manufacturerinjection.class.php`, `inc/groupinjection.class.php`
- Pattern: Adapter classes extend a GLPI item class and implement `PluginDatainjectionInjectionInterface`. Most adapters call `Search::getOptions()` for their parent GLPI type, filter/import-display fields, and delegate persistence to `PluginDatainjectionCommonInjectionLib`.

**CommonInjectionLib:**
- Purpose: Shared validation, formatting, presence detection, add/update processing, and result creation for type adapters.
- Examples: `inc/commoninjectionlib.class.php`, `tests/unit/CommonInjectionLibDateTimeTest.php`, `tests/unit/CommonInjectionLibDataAlreadyInDbTest.php`
- Pattern: Shared service object constructed by each injection adapter with `$values` and `$options`, then driven by `processAddOrUpdate()`.

**Mapping and Info Collections:**
- Purpose: Load and manage model-owned mapping/additional-info rows.
- Examples: `inc/mappingcollection.class.php`, `inc/mapping.class.php`, `inc/infocollection.class.php`, `inc/info.class.php`
- Pattern: Thin collection wrappers around GLPI DB rows, loaded by model ID and consumed by `PluginDatainjectionModel` and `PluginDatainjectionEngine`.

**ClientInjection Workflow Controller:**
- Purpose: Coordinates user-facing upload, import, batch progress, result rendering, and CSV error export.
- Examples: `inc/clientinjection.class.php`, `front/clientinjection.form.php`, `ajax/inject_batch.php`, `templates/clientinjection_injection.html.twig`
- Pattern: Static/instance methods called by GLPI entry scripts and templates; uses `PluginDatainjectionSession` for workflow state.

## Entry Points

**Plugin Bootstrap:**
- Location: `setup.php`
- Triggers: GLPI plugin loading.
- Responsibilities: Define version/compatibility/upload constants, register hooks, declare metadata, populate injectable types, and return plugin URL.

**Install/Uninstall/Migrations:**
- Location: `hook.php`
- Triggers: GLPI plugin install, update, and uninstall lifecycle.
- Responsibilities: Create/drop plugin DB tables, run versioned migrations, create initial rights, remove upload directory, and migrate plugin-specific data.

**Import Screen:**
- Location: `front/clientinjection.form.php`
- Triggers: Browser requests from GLPI tools menu/config page.
- Responsibilities: Check `plugin_datainjection_use`, render model selection/upload UI, process uploaded files, start import workflow, finish/cancel import sessions.

**Model Management:**
- Location: `front/model.php`, `front/model.form.php`
- Triggers: Browser requests to model list/detail/actions.
- Responsibilities: List models, create/update/delete/purge models, upload sample files, generate mappings, validate models, download CSV samples.

**Mapping Management:**
- Location: `front/mapping.form.php`
- Triggers: Model mapping form submission.
- Responsibilities: Save mapping rows, require at least one mandatory/link field, advance model workflow step.

**Additional Info Management:**
- Location: `front/info.form.php`
- Triggers: Additional information form submission.
- Responsibilities: Persist model-owned values that the importer must request outside the CSV file.

**Batch Import API:**
- Location: `ajax/inject_batch.php`
- Triggers: jQuery AJAX from `public/js/injection_progress.js`.
- Responsibilities: Process one offset/batch-size slice and return JSON progress.

**Result Rendering API:**
- Location: `ajax/results.php`
- Triggers: AJAX load after final batch and direct result partial requests.
- Responsibilities: Render final result state using the current session model.

## Error Handling

**Strategy:** Use GLPI session messages for user-correctable form/import issues, return structured status arrays for import checks, and log unexpected exceptions through GLPI's error handler during batch processing.

**Patterns:**
- User-facing validation failures call `Session::addMessageAfterRedirect(...)` in `front/clientinjection.form.php`, `front/model.form.php`, `front/mapping.form.php`, and `inc/model.class.php`.
- File/model validation methods return arrays with `status`, `message`, `field_in_error`, or `error_message` in `inc/model.class.php`.
- Batch import catches `Throwable` per line in `PluginDatainjectionClientInjection::processBatch()` and logs it with `ErrorHandler::logCaughtException()` before marking the line failed.
- Backend factory rejects unknown file types with `InvalidArgumentException` in `PluginDatainjectionBackend::getInstance()`.
- `PluginDatainjectionInjectionType::dropdownLinkedTypes()` throws `Glpi\Exception\Http\HttpException` when the requested primary type is not a valid GLPI database class.

## Cross-Cutting Concerns

**Logging:** GLPI error logging is used for exceptions in `inc/clientinjection.class.php`. Import business results are gathered into session-backed result arrays and log/result rendering through `inc/model.class.php` and `templates/log_results.html.twig`.

**Validation:** GLPI rights checks gate controllers and model access. File validation checks CSV extension, line counts, headers, mappings, mandatory fields, date/float/bool/string formats, and per-type import rules through `inc/model.class.php`, `inc/backendcsv.class.php`, `inc/engine.class.php`, `inc/commoninjectionlib.class.php`, and `inc/*injection.class.php`.

**Authentication:** Authentication is GLPI-owned. Entry points use `Session::checkLoginUser()`, `Session::checkRight()`, or `Session::checkCentralAccess()` in `front/*.php` and `ajax/*.php`; model visibility is enforced in `PluginDatainjectionModel::canViewItem()` and `PluginDatainjectionModel::canCreateItem()`.

---

*Architecture analysis: 2026-08-20*
