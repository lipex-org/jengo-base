# Jengo Base

The core package for Jengo applications, providing essential utilities, installers, and structure for CodeIgniter 4 rapid development.

## Features

- **Rapid Setup**: Command-line installers for common stacks.
- **Frontend Integration**: Seamless support for modern frontend tools like Vite.
- **Helpers & Utilities**: Specialized helpers for Jengo's architecture.

## Installers

Jengo Base includes several installers to jumpstart your development.

### [Vite Installer](docs/installers/vite.md)

Sets up a complete Vite environment with optional Tailwind CSS support.

```bash
php spark jengo:install vite
```

### Blueprint Installer

Sets up the core UI architecture (Layouts, Partials, Home Page).

```bash
php spark jengo:install blueprint
```

### The Guardian (Health Check)

A diagnostic tool that ensures your application is correctly configured and healthy.

```bash
php spark jengo:health
```

### The Auditor (Security Check)

Audit your project for security misconfigurations and best practices.

```bash
php spark jengo:audit
```

### The Observer (Log Streamer)

Stream your application logs in real-time with coloring and filtering.

```bash
php spark jengo:tail-log
```

### System Integration Hub

Connect your application to external ecosystems (Auth, SPA, API) through a unified wizard.

```bash
php spark jengo:setup
```

Or run a specific integration directly:

```bash
php spark jengo:setup auth
php spark jengo:setup inertia
php spark jengo:setup api
```

### Core Generators

Jengo provides several generators to maintain a consistent architecture.

```bash
php spark jengo:make-action     # Generate a single-action class
php spark jengo:make-event      # Generate an event class
php spark jengo:make-layout     # Generate a UI layout
php spark jengo:make-page       # Generate a UI page
php spark jengo:make-repo       # Generate a repository
```

### The Vault (API Suite)

Provides a professional API foundation with JWT support and standardized JSON responses.

Once established, your application is equipped with a standard `APIController` and JWT authentication utilities.

### Modern Features: Controller Attributes

Jengo leverages CI4's new Controller Attributes to provide a declarative developer experience.

-   **`#[API]`**: Apply this attribute to any class or method to automatically force JSON responses and wrap output in the standard Jengo structure.

## 🛠 Helpers & Utilities

Jengo Base provides several helper functions to simplify common tasks. Ensure the helper is loaded: `helper('jengo')`.

### UI Helpers
- **`page(string $name, array $data = [])`**: Renders a view from `app/Views/pages/`.
- **`vite_tags()`**: Injects the necessary `<script>` and `<link>` tags for Vite entrypoints.

### Event System
- **`register_events(...$events)`**: Registers one or more `AbstractEvent` classes.
- **`trigger_event(string $event, ...$args)`**: Triggers a Jengo-style event.

### General Utilities
- **`model_of(string $model)`**: Returns a `ModelFacade` for fluent model interaction.
- **`isProduction()`, `isDevelopment()`, `isTesting()`**: Environment check shorthands.
- **`controller_url(string $controller, string $method, ...$args)`**: Type-safe URL generation for controllers.

## ⚙ Setup

To register the Jengo helper in your application's `Autoload.php`:

```bash
php spark jengo:setup
```

## Installation

Install the package via Composer:

```bash
composer require jengo/base
```

## Usage

Register the package in your CodeIgniter 4 application and use the provided `spark` commands.

```bash
php spark list
```

Look for the `jengo` namespace to see available commands.