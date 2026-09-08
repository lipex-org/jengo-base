# Jengo Base

The foundational package of the Jengo ecosystem, providing core CLI tooling, Blueprint UI layouts, helper libraries, and runtime architecture for CodeIgniter 4 applications.

Documentation: https://lipex-org.github.io/jengophp.com/packages/base

## Installation

```bash
composer require jengo/base
```

## Quick Start

```php
// Use the global Jengo helpers
page('dashboard/index', ['stats' => $stats]);
str('jengo_powerhouse')->headline(); // "Jengo Powerhouse"
arr([1, 2, 3])->map(fn($v) => $v * 2)->toArray(); // [2, 4, 6]
```

## Documentation

For full guides, CLI command variants, and architectural blueprints, see the documentation at https://lipex-org.github.io/jengophp.com/packages/base.

## License

Released under the MIT License.