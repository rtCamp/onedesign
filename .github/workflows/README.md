# GitHub Workflows

Most jobs are implemented by [rtCamp/plugin-skeleton-d](https://github.com/rtCamp/plugin-skeleton-d/blob/main/.github/workflows/README.md)'s reusable workflows, pinned to a commit SHA. This repository only decides when they run and with which settings.

## Workflows

### [`ci.yml`](ci.yml)

Runs on pull requests to `main` and `release/**`, pushes to `main`, and manual dispatch. `Detect Changes` decides which checks to run from the changed files. Draft PRs skip it, so they only build the zip.

| Job                | Runs when                                     | What                                                                       |
| ------------------ | --------------------------------------------- | -------------------------------------------------------------------------- |
| `actionlint`       | Workflow or `.github/actionlint.yaml` changes | actionlint and shellcheck on the GitHub workflows                          |
| `PHPCS`            | PHP, Composer or `.phpcs.xml.dist` changes    | PHPCS coding standards                                                     |
| `PHPStan`          | PHP, Composer or `phpstan.neon.dist` changes  | PHPStan static analysis                                                    |
| `CSS/JS Lint`      | JS, TS, CSS or their config changes           | ESLint, TypeScript, Stylelint, Prettier                                    |
| `Jest Unit Tests`  | JS or Jest test changes                       | Jest, with coverage uploaded to Codecov                                    |
| `PHPUnit`          | PHP or PHPUnit test changes                   | PHPUnit on PHP 8.2–8.5 with the latest WordPress, coverage on 8.5          |
| `E2E Tests`        | PHP, JS, CSS or E2E test changes              | Playwright E2E tests against wp-env                                        |
| `Build Plugin Zip` | Always                                        | Builds `onedesign.zip`. On PRs, also uploads it for the Playground preview |

Changing `ci.yml` itself runs every check, so bumping the pinned workflows re-tests everything.

### [`wp-playground-pr-preview.yml`](wp-playground-pr-preview.yml)

Runs after `ci.yml` succeeds on a PR. Uploads the PR's zip to the `ci-artifacts` prerelease, points [`blueprint.json`](../../blueprint.json) at it, and adds a "Preview in WordPress Playground" button to the PR description.

It never checks out PR code: the zip is built in `ci.yml` without write permissions, and only published here.

### [`pr-cleanup.yml`](pr-cleanup.yml)

Runs when a PR is closed or merged. Cancels the PR's in-progress runs and waits for them, and for any preview still publishing, to stop. It then deletes the Actions artifacts from all of the PR's runs and its zips from `ci-artifacts`, so its preview button stops working.

It uses `pull_request_target` so that PRs from forks get a token that can delete artifacts, so it must never check out or run PR code.

### [`release.yml`](release.yml)

Runs on pushes to `main`. [release-please](https://github.com/googleapis/release-please) maintains a release PR from conventional commits. Merging it creates the release, and this workflow builds `onedesign.zip` and attaches it.

### [`pr-title.yml`](pr-title.yml)

Validates that PR titles follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/), which release-please relies on.

### [`copilot-setup-steps.yml`](copilot-setup-steps.yml) and [`copilot-code-review.yml`](copilot-code-review.yml)

Set up the environment for the GitHub Copilot coding agent and Copilot code review.

## Configuration

- **PHP version:** `php-version` on the `ci.yml` and `release.yml` jobs, plus the `phpunit` matrix in `ci.yml`.
- **Plugin slug:** `plugin-slug: onedesign` is passed to the build, PHPUnit, E2E and Playground workflows.
- **Shared workflows:** Dependabot bumps the pinned SHAs along with other GitHub Actions. Changes to what a job does belong in [rtCamp/plugin-skeleton-d](https://github.com/rtCamp/plugin-skeleton-d); its README lists each workflow's inputs.

`wp-playground-pr-preview.yml`, `pr-cleanup.yml` and `pr-title.yml` always run from the default branch, so changes to them only take effect once merged. If the `ci.yml` workflow `name` changes, update `workflows:` in `wp-playground-pr-preview.yml` too.

### Secrets

| Secret          | Used by                          | Notes                                                |
| --------------- | -------------------------------- | ---------------------------------------------------- |
| `CODECOV_TOKEN` | `ci.yml` (PHPUnit and Jest jobs) | Optional — coverage uploads fail silently without it |

## Testing Workflows Locally

You can use [act](https://github.com/nektos/act) to test GitHub workflows locally. The examples below use inline inputs and inline secrets only (no external JSON or .env files).

```bash
# List workflows available in this repo
act -l

# Run the full CI as a push event (map ubuntu-24.04 to an act-compatible image)
act push -P ubuntu-24.04=catthehacker/ubuntu:act-latest

# Run the `detect` job for a pull request event
act pull_request -j detect -P ubuntu-24.04=catthehacker/ubuntu:act-latest

# Trigger `ci.yml` via workflow_dispatch and run the `phpunit` job with specific inputs and secrets
act workflow_dispatch \
	--input php-version=8.2 \
	--input wp-version=latest \
	--input coverage=true \
	-j phpunit \
	-s CODECOV_TOKEN=your_codecov_token_here \
	-s GITHUB_TOKEN=your_github_token_here \
	-P ubuntu-24.04=catthehacker/ubuntu:act-latest
```
