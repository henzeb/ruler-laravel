# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`henzeb/ruler-laravel` is a Laravel package that allows custom `Rule` class objects to be used as string-based validator rules (e.g., `'enum:App\Enums\MyEnum'` instead of `new Enum(MyEnum::class)`). It pre-registers Laravel's built-in `Enum` rule out of the box.

## Commands

```bash
# Run tests
vendor/bin/pest

# Run a single test
vendor/bin/pest --filter="test method name"

# Testdox output
vendor/bin/pest --testdox

# Code coverage
XDEBUG_MODE=coverage vendor/bin/pest --coverage
```

## Architecture

**Namespace:** `Henzeb\Ruler`

### Core Components

- **`Concerns/Ruler` trait** - Heart of the package. Used by service providers to register rule classes with Laravel's Validator. Handles interface detection (via a classmap), validator extension (`extend`/`extendImplicit`/`extendDependent`), message replacer registration, and rule instantiation at validation time.

- **`Providers/RulerServiceProvider`** - Auto-discovered service provider. Uses the `Ruler` trait. Registers a custom Validator resolver (returning `RulerValidator`) and bootstraps the `$rules` array (currently just `Enum::class`).

- **`Validator/RulerValidator`** - Extends Laravel's Validator. Overrides `getMessage()` to support closure-based dynamic messages and `messages()` to flatten array messages. Owns a static `$rulers` property that stores rule instances during validation for message retrieval.

- **`Contracts/ReplacerAwareRule`** - Interface for rules that want named parameter placeholders (e.g., `:shouldEqual`) instead of positional (`:0`, `:1`).

### Key Design Details

- **Interface-driven dispatch:** The `Ruler` trait checks which interfaces a rule implements (e.g., `ImplicitRule`, `DataAwareRule`, `InvokableRule`, `ValidationRule`) to determine the correct Validator extension method. A rule can implement multiple interfaces.

- **Static instance store:** `RulerValidator::$rulers` stashes the running rule instance so the message closure (registered at boot time) can retrieve it at validation time.

- **Reflection:** `newInstanceWithoutConstructor()` is used for interface detection at registration time. Actual instantiation during validation uses `new $extension(...$parameters)`.

## Testing

Uses Pest v4 with `orchestra/testbench`. Test fixtures in `tests/Fixtures/` provide various rule implementations (basic, parameterized, implicit, dependent, invokable, replacer-aware, dynamic message, etc.). Supports PHP 8.3+ and Laravel 11-12.