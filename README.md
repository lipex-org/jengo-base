<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/">
    <img src="https://raw.githubusercontent.com/lipex-org/docs/main/public/logo-full.png" width="220" alt="Jengo Logo">
  </a>
</p>

<h1 align="center">Jengo Base</h1>

<p align="center">
  <strong>The foundational core of the Jengo Framework providing dynamic command variants, package installers, ID obfuscation (Sqids), form handlers, response modifiers, and dependency injection.</strong>
</p>

<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/packages/base"><strong>Documentation</strong></a> •
  <a href="https://github.com/lipex-org/base/blob/main/LICENSE"><strong>License</strong></a> •
  <a href="https://github.com/lipex-org/base/issues"><strong>Issues</strong></a>
</p>

---

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