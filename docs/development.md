# Development

```console
composer check     # cs:check + phpstan (max) + phpunit
```

No database is needed anywhere: this bundle owns no entities. The integration
suite writes real bytes into a temp directory, because a mocked filesystem would
only be testing the mock.

CI runs PHP 8.4 and 8.5 with **GD but deliberately without Imagick**, so the
"a HEIC arrived and nothing here can decode it" branch is exercised on every
run rather than only on the machines that happen to lack the extension.
