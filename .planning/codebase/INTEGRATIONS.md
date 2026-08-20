# External Integrations

**Analysis Date:** 2026-08-20

## APIs & External Services

**GLPI Core Runtime:**
- GLPI plugin API - Provides lifecycle hooks, menu registration, rights checks, template rendering, DB access, search options, AJAX helpers, sessions, and migrations.
  - SDK/Client: GLPI core classes/functions used directly from plugin PHP files, not through this plugin's `composer.json`.
  - Auth: GLPI session and profile rights, checked with `Session::checkRight`, `Session::checkLoginUser`, `Session::checkCentralAccess`, `Session::haveRight`, and `Session::haveAccessToEntity` in `front/*.php`, `ajax/*.php`, `inc/model.class.php`, `inc/clientinjection.class.php`, and `inc/profile.class.php`.

**GLPI Plugin Ecosystem:**
- GLPI plugin hooks - `setup.php` registers hooks including `csrf_compliant`, `migratetypes`, `config_page`, `menu_toadd`, `pre_item_purge`, `add_css`, and `add_javascript`.
  - SDK/Client: GLPI `Plugin` and global `$PLUGIN_HOOKS`.
  - Auth: GLPI plugin activation state and GLPI rights.
- Third-party injectable types - `setup.php` calls `Plugin::doHook('plugin_datainjection_populate')` so other GLPI plugins can add injectable types to `$INJECTABLE_TYPES`.
  - SDK/Client: GLPI hook system.
  - Auth: GLPI plugin/runtime permissions.
- Optional GLPI PDF plugin - `inc/clientinjection.class.php` checks `$plugin->isActivated('pdf')` before exposing PDF export actions in `templates/clientinjection_result.html.twig`.
  - SDK/Client: GLPI `Plugin`.
  - Auth: GLPI plugin activation state.

**Translation Service:**
- Transifex - Locale sync is configured in `.tx/config`; GitHub Actions workflows `.github/workflows/locales-sync.yml` and `.github/workflows/locales-update-source.yml` call GLPI translation workflow templates.
  - SDK/Client: Transifex configuration in `.tx/config` and reusable GitHub Actions from `glpi-project/plugin-translation-workflows`.
  - Auth: GitHub Actions secrets `TRANSIFEX_TOKEN` and `LOCALES_SYNC_TOKEN` referenced by `.github/workflows/locales-sync.yml` and `.github/workflows/locales-update-source.yml`.

**Release/Marketplace Metadata:**
- GLPI plugins catalog/GitHub releases - `datainjection.xml` contains homepage, release download URLs, issue URL, readme URL, logo URL, screenshots, versions, and compatibility metadata.
  - SDK/Client: XML metadata file `datainjection.xml`.
  - Auth: Not required at plugin runtime.
- GitHub reusable release workflows - `.github/workflows/auto-tag-new-version.yml` and `.github/workflows/release.yml` use `glpi-project/plugin-release-workflows`.
  - SDK/Client: GitHub Actions reusable workflows.
  - Auth: `AUTOTAG_TOKEN` for auto-tagging; release workflow uses GitHub `contents: write` permissions.

## Data Storage

**Databases:**
- GLPI database through GLPI's `$DB` object.
  - Connection: GLPI core database configuration, not stored in this repository.
  - Client: GLPI `DBmysql`/`$DB`, `CommonDBTM`, `CommonDBChild`, `Migration`, and `DBConnection`.
  - Plugin tables created/managed in `hook.php`:
    - `glpi_plugin_datainjection_models`
    - `glpi_plugin_datainjection_modelcsvs`
    - `glpi_plugin_datainjection_mappings`
    - `glpi_plugin_datainjection_infos`
    - `glpi_plugin_datainjection_profiles`
  - Runtime table access examples:
    - `inc/model.class.php` queries `glpi_plugin_datainjection_models`.
    - `inc/mappingcollection.class.php` queries `glpi_plugin_datainjection_mappings`.
    - `inc/infocollection.class.php` queries `glpi_plugin_datainjection_infos`.
    - `inc/modelcsv.class.php` queries the model-specific CSV table through `CommonDBChild`.

**File Storage:**
- Uploaded CSV storage: `PLUGIN_DATAINJECTION_UPLOAD_DIR`, defined in `setup.php` as `GLPI_PLUGIN_DOC_DIR . "/datainjection/"`.
- Uploaded files are copied with `move_uploaded_file` in `inc/model.class.php`, parsed by `inc/backendcsv.class.php`, and deleted through `PluginDatainjectionBackendcsv::deleteFile()`.
- Large session data storage: `inc/session.class.php` writes `results`, `error_lines`, `injection_lines`, `injection_results`, and `injection_error_lines` to files under `GLPI_TMP_DIR` instead of keeping large arrays directly in `$_SESSION`.
- Static assets: plugin images live in `pics/`, screenshots live in `screenshots/`, JavaScript lives in `public/js/`, and CSS lives in `public/css/`.

**Caching:**
- Runtime caching service: Not detected.
- Tool cache: PHPUnit is configured with `cacheDirectory="var/phpunit"` in `phpunit.xml`; Rector cache is configured under `sys_get_temp_dir() . '/datainjection-rector'` in `rector.php`.

## Authentication & Identity

**Auth Provider:**
- GLPI built-in authentication and authorization.
  - Implementation: HTTP entry points call GLPI session checks. Examples include `Session::checkRight("plugin_datainjection_use", READ)` in `front/clientinjection.form.php`, `Session::checkLoginUser()` in `front/model.php` and `front/model.form.php`, `Session::checkRight('plugin_datainjection_model', UPDATE)` in `front/mapping.form.php`, and `Session::checkCentralAccess()` in `ajax/*.php`.
  - Rights: `PluginDatainjectionProfile::getAllRights()` in `inc/profile.class.php` defines `plugin_datainjection_model` and `plugin_datainjection_use`.
  - Profile integration: `setup.php` registers `PluginDatainjectionProfile` on the GLPI `Profile` tab and `hook.php` creates initial access rights during install.

## Monitoring & Observability

**Error Tracking:**
- External error tracking service: None detected.
- GLPI error logging: `inc/clientinjection.class.php` catches `Throwable` and logs caught exceptions with `Glpi\Error\ErrorHandler::logCaughtException()` during batch processing.

**Logs:**
- Import result logs are stored in GLPI's log table and displayed/exported by `inc/model.class.php` methods such as `prepareLogResults()`, `showLogResults()`, and `exportAsPDF()`.
- User-facing import results are stored in session-backed files by `inc/session.class.php` and rendered by `templates/clientinjection_result.html.twig` and `templates/log_results.html.twig`.

## CI/CD & Deployment

**Hosting:**
- Production hosting is GLPI plugin deployment. The repository does not define a standalone web server, Dockerfile, or application hosting config.
- Release packaging is handled through GLPI reusable GitHub Actions workflows in `.github/workflows/release.yml`.

**CI Pipeline:**
- GitHub Actions:
  - `.github/workflows/continuous-integration.yml` generates a matrix for GLPI `11.0.x` and runs the GLPI reusable continuous integration workflow.
  - `.github/workflows/auto-tag-new-version.yml` auto-tags version changes when `setup.php` changes on `main` or `**/bugfixes`.
  - `.github/workflows/release.yml` publishes releases on tags.
  - `.github/workflows/locales-sync.yml` syncs translations from Transifex.
  - `.github/workflows/locales-update-source.yml` pushes locale sources to Transifex.
  - `.github/workflows/close_stale_issue.yml` runs `actions/stale`.
  - `.github/workflows/label-commenter.yml` comments on issue label changes.

## Environment Configuration

**Required env vars:**
- Runtime plugin code does not read repository-defined environment variables.
- GLPI runtime supplies database, session, filesystem, and URL settings through GLPI constants/globals such as `GLPI_PLUGIN_DOC_DIR`, `GLPI_TMP_DIR`, `$CFG_GLPI`, and `$DB`.
- CI secrets referenced by workflows:
  - `AUTOTAG_TOKEN` in `.github/workflows/auto-tag-new-version.yml`
  - `TRANSIFEX_TOKEN` in `.github/workflows/locales-sync.yml` and `.github/workflows/locales-update-source.yml`
  - `LOCALES_SYNC_TOKEN` in `.github/workflows/locales-sync.yml`

**Secrets location:**
- Runtime secrets are owned by GLPI installation configuration, not this plugin repository.
- GitHub Actions secrets are referenced by workflow files but values are not stored in the repository.
- No `.env`, `.env.*`, or `*.env` files were detected in the repository root or first-level directories.

## Webhooks & Callbacks

**Incoming:**
- Browser/GLPI HTTP entry points:
  - Page controllers: `front/clientinjection.form.php`, `front/model.php`, `front/model.form.php`, `front/mapping.form.php`, `front/info.form.php`, `front/popup.php`, `front/export.csv.php`, `front/export.pdf.php`.
  - AJAX callbacks: `ajax/dropdownSelectModel.php`, `ajax/dropdownChooseField.php`, `ajax/dropdownMandatory.php`, `ajax/injection.php`, `ajax/inject_batch.php`, `ajax/results.php`.
- GLPI lifecycle hooks:
  - `plugin_init_datainjection()` and `plugin_version_datainjection()` in `setup.php`.
  - `plugin_datainjection_install()` and `plugin_datainjection_uninstall()` in `hook.php`.
  - Migration hook `plugin_datainjection_migratetypes_datainjection()` in `setup.php`.

**Outgoing:**
- Runtime outbound webhooks/API calls: Not detected.
- Repository metadata points to external project URLs in `README.md` and `datainjection.xml`, but runtime code does not call those services.
- CI workflows call GitHub reusable workflows and Transifex workflows from `.github/workflows/*.yml`.

---

*Integration audit: 2026-08-20*
