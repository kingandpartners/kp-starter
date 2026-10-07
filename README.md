# KP Starter Plugin

Opinionated set of base plugins and features for WordPress used by King & Partners.

The package owns shared Bedrock configuration, runtime bootstrapping, reusable
features, and the multisite ACF relationship field. A project keeps its
application-specific theme and content definitions in its own repository.

`PROJECT_ROOT` is the consumer repository root used to discover `cms/` and
`src/`. Bedrock resolves its own root from the installed package location. Set
`WORDPRESS_ROOT` only when a project uses a nonstandard WordPress layout.

## Validation

Run the package smoke test with:

```sh
composer test
```

The smoke test verifies that the Composer artifact contains the required
bootstrap, configuration, runtime, ACF, and translation files, and runs PHP
syntax checks over every packaged PHP file.
