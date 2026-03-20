# Coding Standards

## PHP

- Always use `use` statements at the top of PHP files. Never use fully-qualified class names inline.
- Use PHP 8 constructor property promotion in `__construct()`.
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.
- Prefer PHPDoc blocks over inline comments.

## Database

- Prefer `Model::query()` over `DB::` facade for queries.
- Use Eloquent relationships over raw queries or manual joins.
- Use eager loading to prevent N+1 query problems.
- Use Laravel's query builder only for very complex operations that don't fit Eloquent well.

## Scaffolding

Use `php artisan make:*` commands (with `--no-interaction`) to create new files (migrations, controllers, models, etc.) instead of hand-creating them.
