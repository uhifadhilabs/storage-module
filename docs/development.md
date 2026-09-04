# Development

```console
composer check     # cs:check + phpstan (max) + phpunit
```

The suite needs a **real Postgres** (`STORAGE_TEST_DATABASE_URL`, see
`phpunit.dist.xml`) and this bundle still **owns no entities** — both are true.
The database belongs to `uhifadhi/widget-module`: the Files hub is a widget
dashboard, one person's arrangement of one is a stored row, and the bundle that
owns the row owns its schema. Nothing under `src/` maps a table.

The suite also boots `uhifadhi/team-module`, for one thing only — the account
class every stored layout is keyed by. Team ships dashboards of its own, and
`OnlyThisModulesSurfacesPass` clears their tag so an assertion about the widget
registry stays an assertion about storage rather than about a dependency's
release notes.

The integration suite writes real bytes into a temp directory, because a mocked
filesystem would only be testing the mock.

CI runs PHP 8.4 and 8.5 with **GD but deliberately without Imagick**, so the
"a HEIC arrived and nothing here can decode it" branch is exercised on every
run rather than only on the machines that happen to lack the extension.
