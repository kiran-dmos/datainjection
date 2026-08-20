# Technology Stack

**Analysis Date:** 2026-08-20

## Languages

**Primary:**
- PHP >=8.2 - Plugin runtime code lives in `setup.php`, `hook.php`, `front/*.php`, `ajax/*.php`, and `inc/*.class.php`. `composer.json` requires `"php": ">=8.2"` and pins Composer's platform PHP to `8.2.99`.

**Secondary:**
- Twig - Server-rendered UI templates live in `templates/*.html.twig` and are rendered with `Glpi\Application\View\TemplateRenderer` from classes such as `inc/clientinjection.class.php`, `inc/model.class.php`, and `inc/mapping.class.php`.
- JavaScript - Browser-side UI behavior lives in `public/js/datainjection.js` and `public/js/injection_progress.js`. The batch import UI uses jQuery AJAX in `public/js/injection_progress.js`.
- CSS - Plugin styling lives in `public/css/datainjection.css`, which is registered through `$PLUGIN_HOOKS['add_css']['datainjection']` in `setup.php`.
- XML - GLPI plugin marketplace metadata lives in `datainjection.xml`.
- PO/MO gettext files - Translation sources and compiled locales live in `locales/*.po`, `locales/*.mo`, and `locales/datainjection.pot`.

## Runtime

**Environment:**
- GLPI plugin runtime - The plugin is loaded by GLPI through `setup.php` and install/uninstall hooks in `hook.php`.
- PHP >=8.2 - Required by `composer.json`.
- GLPI 11.0.5 through 11.0.x - `setup.php` defines `PLUGIN_DATAINJECTION_MIN_GLPI` as `11.0.5` and `PLUGIN_DATAINJECTION_MAX_GLPI` as `11.0.99`; `datainjection.xml` declares version `2.15.10` compatible with `~11.0.5`.

**Package Manager:**
- Composer - `composer.json` and `composer.lock` are present.
- Lockfile: present at `composer.lock`.
- No Node package manifest detected - `package.json`, `package-lock.json`, `yarn.lock`, and `pnpm-lock.yaml` are not present. JavaScript tooling is inherited from the parent GLPI repository through `eslint.config.mjs`.

## Frameworks

**Core:**
- GLPI 11 plugin framework - Plugin registration, menu entries, rights, CSRF compliance, migrations, DB access, sessions, templates, and UI helpers are implemented against GLPI classes/functions in `setup.php`, `hook.php`, `front/*.php`, `ajax/*.php`, and `inc/*.class.php`.
- GLPI CommonDBTM/CommonDBChild model layer - Persistent plugin records extend GLPI database base classes, including `PluginDatainjectionModel extends CommonDBTM` in `inc/model.class.php`, `PluginDatainjectionMapping extends CommonDBTM` in `inc/mapping.class.php`, `PluginDatainjectionInfo extends CommonDBTM` in `inc/info.class.php`, and `PluginDatainjectionModelcsv extends CommonDBChild` in `inc/modelcsv.class.php`.
- GLPI TemplateRenderer/Twig - Template rendering is done with `TemplateRenderer::getInstance()->display(...)` in `inc/clientinjection.class.php`, `inc/model.class.php`, and `inc/mapping.class.php`.
- GLPI plugin hook system - `setup.php` registers plugin hooks for CSRF, type migration, profile tabs, config page, menu entry, CSS, JavaScript, and plugin-populated injection types.

**Testing:**
- PHPUnit - Configured by `phpunit.xml`, with tests in `tests/unit/*.php` and bootstrap logic in `tests/bootstrap.php`.
- GLPI test framework - Unit tests extend `Glpi\Tests\DbTestCase` in `tests/unit/GroupInjectionTest.php`, `tests/unit/CommonInjectionLibDateTimeTest.php`, and `tests/unit/CommonInjectionLibDataAlreadyInDbTest.php`.

**Build/Dev:**
- `glpi-project/tools` 0.8.3 - Composer dev dependency in `composer.lock`; provides GLPI plugin development tooling.
- PHP-CS-Fixer - Configured by `.php-cs-fixer.php` with `@PER-CS2.0`.
- PHPStan - Configured by `phpstan.neon`, scanning `ajax`, `front`, `inc`, `hook.php`, and `setup.php` at level 5 with GLPI/PHPStan extensions from the parent repository.
- Psalm - Configured by `psalm.xml` with taint analysis enabled for `ajax`, `front`, `inc`, `hook.php`, and `setup.php`.
- Rector - Configured by `rector.php` for PHP 8.2 and paths `ajax`, `front`, and `inc`.
- ESLint - Configured by `eslint.config.mjs`, which imports the parent GLPI ESLint config from `../../eslint.config.mjs`.
- Stylelint - Configured by `.stylelintrc.js`, which extends `../../.stylelintrc.js`.
- Twig CS - Configured by `.twig_cs.dist.php`, using `Glpi\Tools\GlpiTwigRuleset` for `templates/*.html.twig`.

## Key Dependencies

**Critical:**
- GLPI core - Not declared as a Composer package in this plugin, but required by runtime code throughout `setup.php`, `hook.php`, `front/*.php`, `ajax/*.php`, and `inc/*.class.php`. The plugin depends on GLPI-provided classes such as `Plugin`, `Session`, `Html`, `Toolbox`, `Migration`, `DBConnection`, `CommonDBTM`, `CommonDBChild`, `Search`, `Dropdown`, and `Ajax`.
- PHP built-in CSV/file APIs - `inc/backendcsv.class.php` uses `fopen`, `fgetcsv`, `fread`, `fclose`, and `unlink` through `thecodingmachine/safe` wrappers to parse uploaded CSV files.
- Twig 3.28.0 - Present in `composer.lock` under `packages-dev`; used indirectly through GLPI tooling and template rendering.

**Infrastructure:**
- `glpi-project/tools` 0.8.3 - Development tooling dependency in `composer.lock`.
- Symfony Console 6.4.43 and Symfony String 7.4.15 - Transitive dev dependencies in `composer.lock`.
- `psr/container` 2.0.2 - Transitive dev dependency in `composer.lock`.

## Configuration

**Environment:**
- Plugin runtime configuration is defined in `setup.php`.
- `PLUGIN_DATAINJECTION_VERSION` is `2.15.10` in `setup.php`.
- Upload storage is defined as `PLUGIN_DATAINJECTION_UPLOAD_DIR`, defaulting to `GLPI_PLUGIN_DOC_DIR . "/datainjection/"` in `setup.php`.
- The plugin creates `PLUGIN_DATAINJECTION_UPLOAD_DIR` at activation/runtime if missing in `setup.php` and during install/update flows in `hook.php`.
- No `.env`, `.env.*`, or `*.env` files were detected in the repository root or first-level directories.

**Build:**
- Composer config: `composer.json`, `composer.lock`
- PHPUnit config: `phpunit.xml`
- Static analysis config: `phpstan.neon`, `psalm.xml`
- Refactoring config: `rector.php`
- PHP style config: `.php-cs-fixer.php`
- JavaScript lint config: `eslint.config.mjs`
- CSS lint config: `.stylelintrc.js`
- Twig lint config: `.twig_cs.dist.php`
- Plugin metadata: `datainjection.xml`
- Makefile integration: `Makefile` includes `../../PluginsMakefile.mk`, so command targets come from the parent GLPI plugin development environment.

## Platform Requirements

**Development:**
- Work inside a GLPI plugin tree where parent paths such as `../../vendor/autoload.php`, `../../src`, `../../tests/bootstrap.php`, and `../../PluginsMakefile.mk` exist. These paths are referenced by `tests/bootstrap.php`, `phpstan.neon`, `rector.php`, and `Makefile`.
- Run Composer from the plugin directory to install dev dependencies from `composer.json`.
- GLPI test/runtime bootstrap must be available for PHPUnit because `tests/bootstrap.php` loads the parent GLPI test bootstrap and activates the `datainjection` plugin.

**Production:**
- Deploy as a GLPI plugin directory named `datainjection`.
- GLPI must load `setup.php` for plugin metadata/hooks and `hook.php` for install/uninstall/migrations.
- Database storage uses GLPI's configured database through `$DB` and plugin tables created in `hook.php`.
- Uploaded CSV/runtime files are stored under `GLPI_PLUGIN_DOC_DIR/datainjection/`; large session payloads are stored in files under `GLPI_TMP_DIR` by `inc/session.class.php`.

---

*Stack analysis: 2026-08-20*
