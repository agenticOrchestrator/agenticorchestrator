# Contributing to Agent Orchestrator

Thank you for considering contributing to Agent Orchestrator! This document outlines the process for contributing to this project.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Install dependencies:

```bash
composer install
```

## Development Workflow

### Running Tests

```bash
composer test
```

### Running Static Analysis

```bash
composer analyse
```

### Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
composer format
```

## Pull Request Process

1. Create a feature branch from `main`:

```bash
git checkout -b feature/your-feature-name
```

2. Make your changes and ensure:
   - All tests pass (`composer test`)
   - Static analysis passes (`composer analyse`)
   - Code style is consistent (`composer format`)

3. Write or update tests for your changes

4. Commit with a clear, descriptive message:

```bash
git commit -m "feat: Add support for custom memory drivers"
```

5. Push to your fork and open a Pull Request against `main`

## Commit Message Convention

This project follows [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` New features
- `fix:` Bug fixes
- `docs:` Documentation changes
- `test:` Adding or updating tests
- `refactor:` Code changes that neither fix bugs nor add features
- `chore:` Maintenance tasks

## Reporting Issues

- Use the [GitHub issue tracker](https://github.com/agenticOrchestrator/agenticorchestrator/issues)
- Include steps to reproduce, expected behavior, and actual behavior
- For security vulnerabilities, email agenticorchestrator@proton.me directly

## Code of Conduct

Be respectful and constructive. We are all here to build something useful together.
