[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=evlist/wp-whobird)
Codespace created by [@evlist/wp-plugin-codespace](https://github.com/evlist/wp-plugin-codespace).

# whoBIRD observations

A WordPress plugin with one block that shows the birds identified by the [whoBIRD](https://github.com/woheller69/whoBIRD)
app for the day, week or month of a post: names from Wikidata, thumbnails from Wikimedia Commons, links to Wikipedia, and
an audio player for the recordings. The user-facing description (installation, FAQ, changelog) is in [`readme.txt`](readme.txt).

Requires WordPress 6.0 and PHP 7.4, with the SQLite3 extension.

## Install

No build step. Copy the repository into `wp-content/plugins/wp-whobird/`, or build an installable zip from git:

```bash
bin/make-zip.sh
```

Then upload the whoBIRD export (and, optionally, the recordings) into the uploads directory and set their paths in
Settings > WhoBird Settings (see `readme.txt`).

## Develop

```bash
composer install
```

```bash
vendor/bin/phpunit
```

```bash
node --test tests/js/block.test.js
```

`composer lint` runs phpcs (WordPress standard); the existing code does not pass it yet (see the open questions).

What is verified, and how: the tests stub WordPress and the external services (Wikidata, Wikimedia, GitHub); none of them
runs against a real WordPress site or a real service. Each slice document says what remains to try by hand.

## Documentation

- [`docs/architecture.md`](docs/architecture.md): how the block works, entry points, stored data, external services, files.
- [`docs/slices/`](docs/slices/README.md): one document per change, written before the code.
- [`docs/open-questions.md`](docs/open-questions.md): points waiting for a decision.
- [`CLAUDE.md`](CLAUDE.md): instructions for Claude Code sessions (conventions shared with Eric's other projects live in
  [`evlist/shared-room`](https://github.com/evlist/shared-room)).

## License

GPL-3.0-or-later. FontAwesome Free (in `third-party/fontawesome/`) keeps its own licenses (icons CC BY 4.0, fonts OFL 1.1,
code MIT).
