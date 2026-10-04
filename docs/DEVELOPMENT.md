# Contributing to the OneDesign as a Developer

Code contributions, bug reports, and feature requests are welcome! The following sections provide guidelines for contributing to this project, as well as information about development processes and testing.

## Table of Contents

- [Contributing to the OneDesign as a Developer](#contributing-to-the-onedesign-as-a-developer)
  - [Table of Contents](#table-of-contents)
  - [Directory Structure](#directory-structure)
  - [Local setup](#local-setup)
    - [Prerequisites](#prerequisites)
    - [Installation](#installation)
    - [Useful Commands](#useful-commands)
    - [Running Tests](#running-tests)
    - [Building the plugin for distribution](#building-the-plugin-for-distribution)
  - [Code Contributions (Pull Requests)](#code-contributions-pull-requests)
    - [Workflow](#workflow)
    - [Code Quality / Code Standards](#code-quality--code-standards)
      - [PHP_CodeSniffer](#php_codesniffer)
      - [PHPStan](#phpstan)
      - [ESLint](#eslint)
      - [TypeScript](#typescript)
      - [Stylelint](#stylelint)
  - [Releasing](#releasing)

## Directory Structure

<details>
<summary> Click to expand </summary>

```bash
.
├── .github/ # GitHub-specific files and CI/CD workflows.
│
│   # Non-php plugin assets.
├── assets
│   └── src            # TypeScript (.ts/.tsx) sources.
│       ├── admin
│       │   ├── multisite-plugin
│       │   │   └── index.tsx
│       │   ├── onboarding
│       │   │   ├── index.tsx
│       │   │   └── page.tsx
│       │   ├── patterns
│       │   │   ├── App.tsx
│       │   │   ├── components
│       │   │   │   ├── AppliedPatternsTab.tsx
│       │   │   │   ├── BasePatternsTab.tsx
│       │   │   │   ├── Category.tsx
│       │   │   │   ├── MemoizedPatternPreview.tsx
│       │   │   │   ├── PatternModal.tsx
│       │   │   │   └── SiteSelection.tsx
│       │   │   ├── index.ts
│       │   │   └── pattern-event.ts
│       │   ├── plugin
│       │   │   └── index.tsx
│       │   ├── settings
│       │   │   └── index.tsx
│       │   └── templates
│       │       ├── App.tsx
│       │       ├── components
│       │       │   ├── BaseSiteTemplates.tsx
│       │       │   ├── BrandSiteTemplates.tsx
│       │       │   ├── MemoizedTemplatePreview.tsx
│       │       │   ├── SiteSelection.tsx
│       │       │   └── TemplateModal.tsx
│       │       ├── index.ts
│       │       └── template-event.ts
│       ├── components
│       │   ├── Dashicons.tsx
│       │   ├── MultiSites.tsx
│       │   ├── SiteModal.tsx
│       │   ├── SiteSettings.tsx
│       │   └── SiteTable.tsx
│       ├── css
│       │   ├── admin.scss
│       │   ├── editor.scss
│       │   ├── onboarding.scss
│       │   └── template.scss
│       ├── hooks
│       │   └── useSitesManagement.ts
│       ├── images
│       │   └── logo.svg
│       ├── js
│       │   ├── admin.ts
│       │   ├── constants.ts
│       │   ├── editor.ts
│       │   ├── main.ts
│       │   └── utils.ts
│       ├── store
│       │   └── index.ts
│       └── types           # Ambient type declarations (*.d.ts).
│           └── wordpress-block-editor.d.ts
│
│   # Project documentation.
├── docs/
│   ├── CODE_OF_CONDUCT.md
│   ├── CONTRIBUTING.md
│   ├── DEVELOPMENT.md      # 👈 You are here.
│   └── SECURITY.md
│
│   # PHP source files.
├── inc
│   ├── Autoloader.php
│   ├── Encryptor.php
│   ├── Main.php
│   ├── Contracts
│   │   ├── Interfaces
│   │   │   └── Registrable.php
│   │   └── Traits
│   │       └── Singleton.php
│   └── Modules
│       ├── Core
│       │   ├── Assets.php
│       │   └── Rest.php
│       ├── Multisite
│       │   ├── Admin.php
│       │   └── Settings.php
│       ├── Post_Types
│       │   ├── Abstract_Post_Type.php
│       │   ├── Admin.php
│       │   ├── Constants.php
│       │   ├── CPT_Restriction.php
│       │   ├── Meta.php
│       │   ├── Pattern.php
│       │   └── Template.php
│       ├── Rest
│       │   ├── Abstract_REST_Controller.php
│       │   ├── Basic_Options_Controller.php
│       │   ├── Multisite_Controller.php
│       │   ├── Patterns_Controller.php
│       │   └── Templates_Controller.php
│       └── Settings
│           ├── Admin.php
│           └── Settings.php
│
│   # Tests
├── tests/
│   ├── _output/ # Generated results and caches.
│   ├── js/      # Jest unit tests (*.test.ts/*.test.tsx) + setup.ts and tsconfig.json.
│   ├── phpunit/ # PHPUnit tests.
│   │
│   └── bootstrap.php # PHPUnit bootstrapper
│
├── languages
│   └── onedesign.pot
│
│   # Build directories
├── build/        # assets built by webpack
├── node_modules/ # Node.js dependencies
├── vendor/       # Composer dependencies
│
├── wp-assets/    # WordPress plugin assets (banners, screenshots)
│
├── onedesign.php # Root plugin entrypoint.
├── uninstall.php # The plugin uninstaller.
│
│   # Important config files.
│   # .dist suffixes mean there may be a user-customized version without the suffix.
├── .browserslistrc
├── .editorconfig
├── .nvmrc
├── .prettierignore
├── .prettierrc.js
├── .stylelintignore
├── .wp-env.json
├── babel.config.js
├── composer.json
├── eslint.config.mjs   # ESLint flat config.
├── jest.config.js
├── package.json
├── phpcs.xml.dist
├── phpstan.neon.dist
├── phpunit.xml.dist
├── README.md
├── stylelint.config.js
├── tsconfig.base.json  # Shared compiler options.
├── tsconfig.json       # Type-checking config (extends tsconfig.base.json).
└── webpack.config.js

```

</details>

## Local setup

To set up locally, clone the repository into plugins directory of your WordPress installation:

### Prerequisites

- [Node.js](https://nodejs.org/): v22+ ([NVM](https://nvm.sh/) recommended )
- [Docker](https://www.docker.com/)
- Composer: (if you prefer to run the Composer tools locally)

You can use Docker and the `wp-env` tool to set up a local development environment, instead of manually installing the specific testing versions of WordPress, PHP, and Composer. For more information, see the [wp-env documentation](https://developer.wordpress.org/block-editor/packages/packages-env/).

### Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/rtCamp/OneDesign.git
   ```

2. Change into the project folder and install the development dependencies:

   ```bash
   ## If you're using NVM, make sure to use the correct Node.js version:
   nvm install && nvm use

   ## Then install the NPM dependencies:
   npm install

   # And the Composer dependencies:
   composer install
   ```

3. Start the local development environment:

   ```bash
   npm run wp-env start
   ```

The WordPress development site will be available at <http://localhost:8888> and the WP Admin Dashboard will be available at <http://localhost:8888/wp-admin/>. You can log in to the admin using the username `admin` and password `password`.


### Useful Commands

#### Installing Dependencies

- `composer install`: Install PHP dependencies.
- `npm install`: Install JavaScript dependencies.

#### Accessing the Local Environment

- `npm run wp-env start`: Start the local development environment.
- `npm run wp-env stop`: Stop the local development environment.
- `npm run wp-env run tests-cli YOUR_CMD_HERE`: Run WP-CLI commands in the local environment.

For more information on using `wp-env`, see the [wp-env documentation](https://developer.wordpress.org/block-editor/packages/packages-env/).

#### Linting and Formatting

- `npm run lint`:          Runs all of the linters below in parallel.
- `npm run lint:css`:      Runs Stylelint on the SCSS/CSS code.
- `npm run lint:css:fix`:  Autofixes Stylelint issues.
- `npm run lint:js`:       Runs ESLint on the TypeScript/JavaScript code.
- `npm run lint:js:fix`:   Autofixes ESLint issues.
- `npm run lint:js:types`: Runs TypeScript's `tsc` to check for type errors.
- `npm run lint:php`:      Runs PHPCS linting on the PHP code.
- `npm run lint:php:fix`:  Autofixes PHPCS linting issues.
- `npm run lint:php:stan`: Runs PHPStan static analysis on the PHP code.

### Running Tests

#### JavaScript / TypeScript

Jest unit tests live in `tests/js` and are configured in [`jest.config.js`](../jest.config.js):

```bash
npm run test:js

# Watch mode:
npm run test:js:watch

# With a coverage report:
npm run test:js:coverage
```

#### PHP

PHPUnit tests can be run using the following command:

```bash
npm run test:php
```

To generate a code coverage report, make sure to start the testing environment with coverage mode enabled:

```bash
npm run wp-env start -- --xdebug=coverage

npm run test:php
```

You should see the html coverage report in the `tests/_output/html` directory and the clover XML report in `tests/_output/php-coverage.xml`.


### Building the plugin for distribution

To build the plugin for distribution, you can use the following commands`:

```bash
# IMPORTANT!: Make sure you've cleaned up any dev-dependencies from Composer first:
composer install --no-dev

# Clean install of node modules.
npm ci

# Create a production-ready build:
npm run build:prod

## Create the zip file for distribution:
npm run plugin-zip
```

## Code Contributions (Pull Requests)

### Workflow

The `develop` branch is used for active development, while `main` contains the current stable release. Always create a new branch from `develop` when working on a new feature or bug fix.

Branches should be prefixed with the type of change (e.g. `feat`, `chore`, `tests`, `fix`, etc.) followed by a short description of the change. For example, a branch for a new feature called "Add new feature" could be named `feat/add-new-feature`.

### Code Quality / Code Standards

This project uses several tools to ensure code quality and standards are maintained:

#### PHP_CodeSniffer

This project uses [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/) to enforce WordPress Coding Standards. We use the [WPGraphQL Coding Standards ruleset](https://github.com/AxeWP/WPGraphQL-Coding-Standards), which is a superset of [WPCS](https://github.com/WordPress/WordPress-Coding-Standards), [VIPCS](https://github.com/Automattic/VIP-Coding-Standards), and [Slevomat Coding Standard](https://github.com/slevomat/coding-standard) tailored for the WPGraphQL ecosystem.

Our specific ruleset is defined in the [`phpcs.xml.dist`](../phpcs.xml.dist) file.

You can run the PHP_CodeSniffer checks using the following command:

```bash
npm run lint:php
```

PHP_CodeSniffer can automatically fix some issues. To fix issues automatically, run:

```bash
npm run lint:php:fix
```

#### PHPStan

This project uses [PHPStan](https://phpstan.org/) to perform static analysis on the PHP code. PHPStan is a PHP Static Analysis Tool that focuses on finding errors in your code without actually running it.

Our specific configuration is defined in the [`phpstan.neon.dist`](../phpstan.neon.dist) file.

You can run PHPStan using the following command:

```bash
npm run lint:php:stan
```

#### ESLint

This project uses [ESLint](https://eslint.org) through `@wordpress/scripts`, `@wordpress/eslint-plugin` and `typescript-eslint` for TypeScript/JavaScript linting, following WordPress coding standards and best practices.

Our specific ESLint configuration is defined in the [`eslint.config.mjs`](../eslint.config.mjs) file, using ESLint's [flat config](https://eslint.org/docs/latest/use/configure/configuration-files) format.

You can run ESLint using:

```bash
npm run lint:js
```

To automatically fix linting issues:

```bash
npm run lint:js:fix
```

#### TypeScript

All front-end source lives in `assets/src` and is written in TypeScript. Compiler options are shared through [`tsconfig.base.json`](../tsconfig.base.json) and extended by [`tsconfig.json`](../tsconfig.json) (type-checking only, `noEmit`) and [`tests/js/tsconfig.json`](../tests/js/tsconfig.json) (test files). Webpack handles the actual transpilation, so type-checking is a separate step:

```bash
npm run lint:js:types
```

#### Stylelint

This project uses [Stylelint](https://stylelint.io/) through `@wordpress/scripts` for SCSS/CSS linting, following WordPress coding standards and best practices.

Our specific Stylelint configuration is defined in the [`stylelint.config.js`](../stylelint.config.js) file, with ignored paths in [`.stylelintignore`](../.stylelintignore).

You can run Stylelint on the stylesheets using:

```bash
npm run lint:css
```

To automatically fix Stylelint issues:

```bash
npm run lint:css:fix
```

## Releasing
1. Ensure all changes are committed and tested.
2. Update changelogs, version numbers, and `n.e.x.t` tags.
3. Push `develop` branch to `main`.
4. Create a new GitHub release draft with the new tag.
