---
title: Contributing
---

# Contributing

`artisanpack-ui/convertkit` is open source. Bug reports, feature requests, and PRs are welcome.

The full contributor guide lives at [`CONTRIBUTING.md`](https://github.com/ArtisanPack-UI/convertkit/blob/main/CONTRIBUTING.md) in the repo root. This page is the short version.

## Getting the source

```bash
git clone git@github.com:ArtisanPack-UI/convertkit.git
cd convertkit
composer install
```

Everything is symlinkable — you can point a Laravel dev app's `composer.json` at your local checkout to iterate:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../convertkit",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "artisanpack-ui/convertkit": "@dev"
    }
}
```

## Branch naming

- `feature/<issue#>-<short-slug>` — new features.
- `bugfix/<issue#>-<short-slug>` — bug fixes.
- `enhancement/<issue#>-<short-slug>` — improvements to existing features.
- `docs/<issue#>-<short-slug>` — documentation-only changes.
- `chore/<issue#>-<short-slug>` — dependencies, tooling, non-code changes.

Cut branches from `release/1.x` for v1 work. Merge back into the same branch. `main` is release-only.

## Running the test suite

```bash
./vendor/bin/pest             # full suite
./vendor/bin/pest --filter=… # single test
./vendor/bin/pest --compact  # summary output
```

Tests live in `tests/Unit/` and `tests/Feature/`. Use Pest's `it()` syntax.

**Every change must be programmatically tested.** Write or update a test, then run the affected tests to make sure they pass.

## Running code style

Two tools:

```bash
./vendor/bin/php-cs-fixer fix    # auto-fix formatting
./vendor/bin/phpcs               # catch what fixer can't
```

Or both at once:

```bash
composer lint     # dry-run + phpcs
composer fix      # apply fixes
```

The formatting rules follow the [ArtisanPack UI spacing convention](https://github.com/ArtisanPack-UI/code-style-pint) — spaces inside parentheses and brackets, aligned `=` and `=>`, single quotes, Yoda conditionals.

## Commit style

- One logical change per commit when practical.
- Present-tense imperative subject: `feat: add feed dry-run endpoint`.
- Reference the issue if one exists: `Closes #11`.
- Sign commits (or add `Co-Authored-By` if pairing).

Conventional-commit-style prefixes (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `perf:`) are welcome but not required.

## Opening a PR

1. Push your branch to your fork (or a feature branch on the main repo if you have write access).
2. Open a **draft** PR against `release/1.x`.
3. Fill in the summary, list the changes, and describe how to test.
4. Mark ready-for-review once tests and CI are green.

CI runs the test suite and lint against every supported PHP + Laravel matrix. Both must pass before a maintainer will merge.

## Security disclosures

Do **not** open a public issue for security vulnerabilities. Email `me@jacobmartella.com` with details. See the repository security policy for the full disclosure process.

## Code of conduct

Be excellent to each other. Full text: [`CODE_OF_CONDUCT.md`](https://github.com/ArtisanPack-UI/convertkit/blob/main/CODE_OF_CONDUCT.md) if the repo carries one; otherwise the [Contributor Covenant](https://www.contributor-covenant.org/) applies.

## Documentation changes

Docs live under `docs/` in this repo and are mirrored to the wiki. Follow the existing structure:

- Kebab-case file names (`getting-started.md`, `feed-admin.md`).
- YAML frontmatter with a `title:` at the top of every page.
- Every subdirectory has a `parent.md` file at the parent level acting as its index.
- Wiki-style links: `[Page Title](Page-Title)` — the target follows the file path with title-casing, and recognized acronyms (REST, API, FAQ) are preserved.

See the sibling [`artisanpack-ui/google`](https://github.com/ArtisanPack-UI/google/tree/main/docs) package's docs for a reference implementation.

## Getting help

- Repo issues: [github.com/ArtisanPack-UI/convertkit/issues](https://github.com/ArtisanPack-UI/convertkit/issues)
- Author email: [me@jacobmartella.com](mailto:me@jacobmartella.com)
