# Codebase Structure

**Analysis Date:** 2026-08-20

## Directory Layout

```text
datainjection/
├── setup.php                    # GLPI plugin metadata, runtime hooks, injectable type registry
├── hook.php                     # Install, uninstall, and database migration hooks
├── composer.json                # PHP/runtime requirement and Composer dev dependency declaration
├── composer.lock                # Locked Composer dev dependency graph
├── datainjection.xml            # GLPI plugin catalog/release metadata
├── Makefile                     # Includes parent GLPI plugin Makefile
├── front/                       # Full-page GLPI controllers and form handlers
├── ajax/                        # Partial HTML and JSON AJAX callbacks
├── inc/                         # Plugin domain classes, GLPI DB models, backends, injection adapters
├── templates/                   # Twig templates rendered through GLPI TemplateRenderer
├── public/css/                  # Plugin CSS registered through GLPI asset hooks
├── public/js/                   # Plugin browser JavaScript registered/loaded by hooks/templates
├── pics/                        # Runtime images/icons used by plugin UI
├── screenshots/                 # Documentation/catalog screenshots
├── locales/                     # gettext PO/MO translations and source POT file
├── tests/                       # PHPUnit bootstrap and GLPI DB-backed unit tests
├── tools/                       # Repository tool support files such as `tools/HEADER`
├── .github/                     # GitHub Actions workflows, templates, and repository automation
├── .tx/                         # Transifex translation config
├── .planning/codebase/          # Generated GSD codebase map documents
├── .php-cs-fixer.php            # PHP-CS-Fixer config
├── .twig_cs.dist.php            # Twig CS config
├── .stylelintrc.js              # CSS lint config
├── eslint.config.mjs            # JavaScript lint config
├── phpstan.neon                 # PHPStan config
├── psalm.xml                    # Psalm config
├── phpunit.xml                  # PHPUnit config
└── rector.php                   # Rector config
```

## Directory Purposes

**Root Plugin Files:**
- Purpose: GLPI discovers and manages the plugin from root-level files.
- Contains: `setup.php`, `hook.php`, `composer.json`, `composer.lock`, `datainjection.xml`, `README.md`, `SECURITY.md`, `LICENSE`, `Makefile`.
- Key files:
  - `setup.php`: runtime registration, plugin version, compatible GLPI range, menu/assets/hooks, injectable type registry.
  - `hook.php`: database install/uninstall/migrations.
  - `datainjection.xml`: plugin catalog metadata, version compatibility, download URLs, screenshots.

**`front/`:**
- Purpose: Browser-facing GLPI pages and form action handlers.
- Contains: PHP scripts that check GLPI rights, process `$_POST`/`$_GET`, call classes from `inc/`, and render GLPI pages.
- Key files:
  - `front/clientinjection.form.php`: import workflow page and upload handler.
  - `front/model.php`: import model list/search page.
  - `front/model.form.php`: import model create/update/delete/validate/upload/sample handler.
  - `front/mapping.form.php`: column mapping save handler.
  - `front/info.form.php`: additional information save handler.
  - `front/popup.php`: preview/log popup renderer.
  - `front/export.csv.php`: failed-line CSV export.
  - `front/export.pdf.php`: PDF log export.

**`ajax/`:**
- Purpose: AJAX endpoints for partial UI updates and batch import processing.
- Contains: Small PHP scripts that call GLPI no-cache/session helpers and delegate to `inc/` classes.
- Key files:
  - `ajax/dropdownSelectModel.php`: update upload/additional-info area when a model is selected.
  - `ajax/dropdownChooseField.php`: populate available field dropdowns for selected item types.
  - `ajax/dropdownMandatory.php`: update mandatory-field controls.
  - `ajax/inject_batch.php`: process one JSON batch during import.
  - `ajax/results.php`: render final result markup.
  - `ajax/injection.php`: render import progress markup for the current model.

**`inc/`:**
- Purpose: Core plugin implementation.
- Contains: GLPI database models, collections, import workflow services, CSV backend classes, session helpers, rights/profile integration, dropdown/menu helpers, and many item-specific injection adapters.
- Key files:
  - `inc/model.class.php`: reusable import model, workflow steps, uploaded-file processing, mapping generation, log export.
  - `inc/modelcsv.class.php`: CSV-specific model settings.
  - `inc/backend.class.php`, `inc/backendcsv.class.php`, `inc/backendinterface.class.php`: file backend abstraction and CSV parser.
  - `inc/engine.class.php`: per-line CSV-to-GLPI field transformation.
  - `inc/commoninjectionlib.class.php`: shared import validation/add/update mechanics.
  - `inc/injectioninterface.class.php`: contract for item-specific injection adapters.
  - `inc/session.class.php`: `$_SESSION['datainjection']` wrapper and file-backed session payload storage.
  - `inc/clientinjection.class.php`: user-facing import controller/service.
  - `inc/mapping.class.php`, `inc/mappingcollection.class.php`: column mapping model and collection.
  - `inc/info.class.php`, `inc/infocollection.class.php`: additional information model and collection.
  - `inc/profile.class.php`: GLPI profile rights integration.
  - `inc/*injection.class.php`: item-specific injection adapters.

**`templates/`:**
- Purpose: Twig view layer for GLPI-compatible UI.
- Contains: `.html.twig` templates using GLPI macros, Bootstrap/Tabler classes, translated strings, and some inline page-specific scripts.
- Key files:
  - `templates/clientinjection.html.twig`: model selector and import form container.
  - `templates/clientinjection_upload_file.html.twig`: upload/additional-info form.
  - `templates/clientinjection_injection.html.twig`: progress UI and batch JS bootstrapping.
  - `templates/clientinjection_result.html.twig`: final import result actions.
  - `templates/model_advanced_form.html.twig`: model settings form.
  - `templates/modelcsv_additional_form.html.twig`: CSV delimiter/header settings.
  - `templates/mappings_form.html.twig`: mapping table UI.
  - `templates/log_results.html.twig`: import log display.

**`public/`:**
- Purpose: Static runtime assets served by GLPI plugin asset paths.
- Contains: `public/css/datainjection.css`, `public/js/datainjection.js`, and `public/js/injection_progress.js`.
- Key files:
  - `public/js/injection_progress.js`: browser loop for AJAX batch import progress.
  - `public/js/datainjection.js`: log table show/hide helper.
  - `public/css/datainjection.css`: plugin-specific styles.

**`pics/`:**
- Purpose: Runtime UI icons/images.
- Contains: images referenced by plugin UI and CSS, including `pics/datainjection.png`, `pics/logo.png`, `pics/ok.png`, `pics/notok.png`, `pics/plus.png`, `pics/minus.png`, `pics/reportpdf.png`, and status imagery.

**`screenshots/`:**
- Purpose: Documentation/catalog media.
- Contains: `screenshots/datainjection.gif`, `screenshots/datainjection_conf.png`, `screenshots/datainjection_mapping.png`, and `screenshots/datainjection_import_success.png`.

**`locales/`:**
- Purpose: gettext translation source and compiled translations.
- Contains: `locales/datainjection.pot`, `locales/*.po`, and `locales/*.mo`.
- Integration: `.tx/config` maps Transifex resources to `locales/<lang>.po`.

**`tests/`:**
- Purpose: PHPUnit tests that run inside the parent GLPI test environment.
- Contains: `tests/bootstrap.php` and `tests/unit/*.php`.
- Key files:
  - `tests/bootstrap.php`: loads GLPI's parent test bootstrap, plugin autoload, installs and activates `datainjection`.
  - `tests/unit/GroupInjectionTest.php`: GLPI DB-backed tests for group assignment during injection.
  - `tests/unit/CommonInjectionLibDateTimeTest.php`: date/time reformat and injection tests.
  - `tests/unit/CommonInjectionLibDataAlreadyInDbTest.php`: database presence check for apostrophes in mandatory fields.

**`.github/`:**
- Purpose: Repository automation.
- Contains: workflows, issue template, Dependabot config, PR template, and label commenter config.
- Key files:
  - `.github/workflows/continuous-integration.yml`: GLPI plugin CI matrix and reusable CI workflow.
  - `.github/workflows/release.yml`: release publishing.
  - `.github/workflows/auto-tag-new-version.yml`: auto-tagging from `setup.php`.
  - `.github/workflows/locales-sync.yml` and `.github/workflows/locales-update-source.yml`: Transifex workflows.

**`.tx/`:**
- Purpose: Translation service configuration.
- Contains: `.tx/config`, mapping Transifex project `glpi-plugin-datainjection` resource `datainjection-pot` to `locales/<lang>.po`.

## Key File Locations

**Entry Points:**
- `setup.php`: GLPI bootstrap and plugin hook registration.
- `hook.php`: install/uninstall/migration lifecycle.
- `front/clientinjection.form.php`: main CSV import workflow.
- `front/model.php`: model list page.
- `front/model.form.php`: model form action handler.
- `front/mapping.form.php`: mapping save handler.
- `front/info.form.php`: additional information save handler.
- `ajax/inject_batch.php`: JSON batch import endpoint.
- `ajax/results.php`: result partial endpoint.

**Configuration:**
- `composer.json`: PHP version and Composer dev dependency declarations.
- `composer.lock`: locked Composer dependency versions.
- `phpunit.xml`: PHPUnit configuration.
- `phpstan.neon`: PHPStan configuration.
- `psalm.xml`: Psalm taint/static-analysis configuration.
- `rector.php`: Rector refactoring configuration.
- `.php-cs-fixer.php`: PHP style configuration.
- `eslint.config.mjs`: JavaScript lint configuration.
- `.stylelintrc.js`: CSS lint configuration.
- `.twig_cs.dist.php`: Twig style configuration.
- `.tx/config`: Transifex locale mapping.
- `.github/workflows/*.yml`: CI, release, stale issue, and translation automation.

**Core Logic:**
- `inc/clientinjection.class.php`: upload/progress/results workflow logic.
- `inc/model.class.php`: import model persistence, file validation, mapping generation, workflow steps.
- `inc/backendcsv.class.php`: CSV parsing, line counting, encoding handling, deletion.
- `inc/engine.class.php`: per-line field mapping and adapter invocation.
- `inc/commoninjectionlib.class.php`: shared import validation/add/update implementation.
- `inc/injectiontype.class.php`: injectable type and linked-field dropdown logic.
- `inc/*injection.class.php`: item-specific GLPI import adapters.

**Testing:**
- `tests/bootstrap.php`: GLPI-aware test bootstrap.
- `tests/unit/*.php`: PHPUnit/GLPI DB-backed unit tests.

## Naming Conventions

**Files:**
- GLPI plugin class files use lowercase class names with `.class.php`: `inc/model.class.php`, `inc/backendcsv.class.php`, `inc/clientinjection.class.php`.
- Item adapters use `{glpi item name}injection.class.php`: `inc/computerinjection.class.php`, `inc/networkequipmentinjection.class.php`, `inc/softwarelicenseinjection.class.php`.
- Join/adaptor files preserve GLPI-style underscores where the underlying GLPI type uses them: `inc/group_userinjection.class.php`, `inc/item_softwareversioninjection.class.php`, `inc/networkport_vlaninjection.class.php`.
- Front controllers use `.php` and often `.form.php` for form handlers: `front/model.form.php`, `front/clientinjection.form.php`, `front/mapping.form.php`.
- AJAX controllers use short endpoint names: `ajax/inject_batch.php`, `ajax/dropdownSelectModel.php`, `ajax/dropdownChooseField.php`.
- Twig templates are snake_case or feature-specific `.html.twig` files: `templates/clientinjection_upload_file.html.twig`, `templates/model_advanced_form.html.twig`.
- Tests use PHPUnit `*Test.php` names under `tests/unit/`: `tests/unit/GroupInjectionTest.php`.

**Directories:**
- Runtime PHP classes belong in `inc/`.
- Full-page controllers belong in `front/`.
- AJAX-only callbacks belong in `ajax/`.
- Twig templates belong in `templates/`.
- Web assets belong in `public/css/` or `public/js/`.
- Translation files belong in `locales/`.
- Tests belong in `tests/unit/`.

## Where to Add New Code

**New Importable GLPI Type:**
- Primary code: add `inc/{type}injection.class.php` implementing `PluginDatainjectionInjectionInterface`.
- Registry: add the adapter class to `$INJECTABLE_TYPES` in `getTypesToInject()` in `setup.php`, unless it is contributed by another plugin through the `plugin_datainjection_populate` hook.
- Shared behavior: use `PluginDatainjectionCommonInjectionLib` from the adapter's `addOrUpdateObject()` method, following examples in `inc/computerinjection.class.php`, `inc/manufacturerinjection.class.php`, and `inc/groupinjection.class.php`.
- Tests: add or extend `tests/unit/*Test.php` with GLPI `DbTestCase` coverage when behavior touches import validation or persistence.

**New File Backend:**
- Primary code: add `inc/backend{type}.class.php` implementing `PluginDatainjectionBackendInterface`.
- Factory: update `PluginDatainjectionBackend::getInstance()` in `inc/backend.class.php`.
- Model-specific settings: add a `PluginDatainjectionModel{type}` class in `inc/model{type}.class.php` if the backend needs persisted settings beyond `PluginDatainjectionModel`.
- UI: add or update Twig templates under `templates/` and controller handling in `inc/model.class.php`/`front/model.form.php`.
- Database: add schema/migrations in `hook.php` for any new persisted settings.

**New Model Workflow Step or Model Field:**
- Primary code: update constants and behavior in `inc/model.class.php`.
- Database schema: update `hook.php` install SQL and add a versioned migration function in `hook.php`.
- Forms: update `templates/model_advanced_form.html.twig`, `templates/model_validation_form.html.twig`, or related templates.
- Form handler: update `front/model.form.php` when the new field/step changes create/update/validate behavior.
- Search/list display: update `PluginDatainjectionModel::rawSearchOptions()` in `inc/model.class.php` if the field should be searchable.

**New Import UI Screen or Full-Page Action:**
- Controller: add or update a file in `front/`.
- Domain logic: keep reusable logic in `inc/` instead of placing it all in `front/`.
- View: add Twig under `templates/` and render it with `TemplateRenderer::getInstance()->display(...)`.
- Rights: call the appropriate `Session::checkLoginUser()`, `Session::checkRight()`, or model `check()` method at the top of the controller.

**New AJAX Behavior:**
- Endpoint: add a PHP file in `ajax/`.
- Access control: use `Session::checkCentralAccess()` or a more specific GLPI rights check.
- UI caller: wire it from Twig (`templates/*.html.twig`) or JavaScript (`public/js/*.js`).
- Response type: set headers explicitly when returning JSON, following `ajax/inject_batch.php`.

**New Templates:**
- Implementation: add `templates/{feature}.html.twig`.
- Rendering: call `TemplateRenderer::getInstance()->display('@datainjection/{feature}.html.twig', $data)` from a class in `inc/`.
- Forms: include `_glpi_csrf_token` when creating POST forms, following `templates/clientinjection.html.twig`.
- Translations: wrap user-facing text with GLPI translation functions/macros in Twig, following existing templates.

**New JavaScript:**
- Implementation: add `public/js/{feature}.js`.
- Registration: register globally through `$PLUGIN_HOOKS['add_javascript']['datainjection']` in `setup.php` if needed on plugin pages, or load from a specific template like `templates/clientinjection_injection.html.twig`.
- Dependencies: existing code assumes GLPI's browser environment and jQuery.

**New CSS:**
- Implementation: add to `public/css/datainjection.css` or add a new file under `public/css/`.
- Registration: update `$PLUGIN_HOOKS['add_css']['datainjection']` in `setup.php` if adding a separate CSS file.

**New Database Table or Column:**
- Install path: update table creation in `plugin_datainjection_install()` in `hook.php`.
- Upgrade path: add a migration function in `hook.php` and call it from the relevant install/update branches.
- Model class: create/update a `CommonDBTM` or `CommonDBChild` class under `inc/`.
- Cleanup: update `plugin_datainjection_uninstall()` and model purge cleanup if the data is plugin-owned.

**New Tests:**
- Unit/integration tests: add `tests/unit/{Feature}Test.php`.
- Bootstrap: rely on `tests/bootstrap.php`; it loads parent GLPI tests, Composer autoload, installs, and activates the plugin.
- Test base: use `Glpi\Tests\DbTestCase` for database-backed plugin behavior, matching current tests.

## Special Directories

**`.planning/codebase/`:**
- Purpose: GSD-generated codebase map documents.
- Generated: Yes.
- Committed: Project-dependent; do not use this directory for runtime plugin code.

**`locales/`:**
- Purpose: Translation source and compiled locale files.
- Generated: Partially. `locales/datainjection.pot`, `locales/*.po`, and `locales/*.mo` are maintained through translation tooling and Transifex workflows.
- Committed: Yes.

**`vendor/`:**
- Purpose: Composer dependencies when installed.
- Generated: Yes.
- Committed: No. It is ignored by config and not present in the current repository listing.

**`node_modules/`:**
- Purpose: Node dependencies if parent tooling installs them.
- Generated: Yes.
- Committed: No. It is ignored by `eslint.config.mjs` and `.stylelintrc.js` and not present in the current repository listing.

**`var/phpunit/`:**
- Purpose: PHPUnit cache directory from `phpunit.xml`.
- Generated: Yes.
- Committed: No runtime source should be placed here.

---

*Structure analysis: 2026-08-20*
