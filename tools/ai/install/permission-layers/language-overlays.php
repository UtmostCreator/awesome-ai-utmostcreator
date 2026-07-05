<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

/** @return array<string,list<array{permission:string,pattern:string,effect:string}>> */
function aiPermissionLanguageOverlays(): array
{
    return [
        // 4 atomic overlay keys (Slice D, docs/tickets/arch-todo-complete-permission-
        // composition-migration/plan.md) — verbatim copies of existing atomic packs
        // (proof.php_lint, proof.phpunit_direct, impl.composer_validate_allow,
        // proof.js_test_lint_typecheck respectively), added so 5 agent compositions can
        // source PHP/JS commands from the language-overlay path (conditionally injectable
        // per detected stack for consumer projects) instead of hand-typed universal packs
        // that would otherwise reach every impl/verify agent regardless of language. The
        // coarse `'php'`/`'js-ts'` keys below are untouched and remain load-bearing for
        // `stacks/php.json`, `stacks/js-ts.json`, and `StackRegistryTest.php` — these 4 are
        // purely additive, no rename/narrowing of the existing keys.
        'php-lint' => aiPermissionEntries('bash', [
            'php -l *' => 'allow',
        ]),
        'php-phpunit' => aiPermissionEntries('bash', [
            'vendor/bin/phpunit *' => 'allow',
            './vendor/bin/phpunit *' => 'allow',
            'phpunit *' => 'allow',
        ]),
        'php-composer-validate' => aiPermissionEntries('bash', [
            'composer validate*' => 'allow',
        ]),
        'js-core' => aiPermissionEntries('bash', [
            'npm test*' => 'allow',
            'npm run test*' => 'allow',
            'npm run lint*' => 'allow',
            'npm run typecheck*' => 'allow',
            'pnpm test*' => 'allow',
            'pnpm run test*' => 'allow',
            'pnpm run lint*' => 'allow',
            'pnpm run typecheck*' => 'allow',
        ]),
        'php' => aiPermissionEntries('bash', [
            'php -l *' => 'allow',
            'vendor/bin/phpunit *' => 'allow',
            './vendor/bin/phpunit *' => 'allow',
            'phpunit *' => 'allow',
            './vendor/bin/paratest *' => 'ask',
            'vendor/bin/paratest *' => 'ask',
            'paratest *' => 'ask',
            'composer validate*' => 'allow',
            'php tools/ai/validate-*.php *' => 'allow',
            'php tools/ai/generate-*.php --check*' => 'allow',
        ]),
        'js-ts' => aiPermissionEntries('bash', [
            'npm test*' => 'allow',
            'npm run test*' => 'allow',
            'npm run lint*' => 'allow',
            'npm run typecheck*' => 'allow',
            'pnpm test*' => 'allow',
            'pnpm run test*' => 'allow',
            'pnpm run lint*' => 'allow',
            'pnpm run typecheck*' => 'allow',
            'yarn test*' => 'allow',
            'yarn lint*' => 'allow',
            'bun test*' => 'allow',
        ]),
        'shell' => aiPermissionEntries('bash', [
            'bash -n scripts/*.sh' => 'allow',
            'bash -n scripts/**/*.sh' => 'allow',
            'bash -n scripts/doctor.sh' => 'allow',
            'bash scripts/doctor.sh' => 'allow',
            'bash scripts/doctor.sh *' => 'allow',
            'shellcheck *' => 'allow',
            'shfmt -d *' => 'allow',
        ]),
        'markdown' => aiPermissionEntries('bash', [
            'markdownlint-cli2 *' => 'allow',
            'lychee *' => 'allow',
        ]),
        'github-actions' => aiPermissionEntries('bash', [
            'actionlint*' => 'allow',
        ]),
        'python' => aiPermissionEntries('bash', [
            'python3 -m pytest*' => 'allow',
            'pytest*' => 'allow',
            'ruff check*' => 'allow',
            'ruff format --check*' => 'allow',
            'mypy *' => 'allow',
            'python3 -m mypy*' => 'allow',
        ]),
        'go' => aiPermissionEntries('bash', [
            'go build*' => 'allow',
            'go vet*' => 'allow',
            'go test*' => 'allow',
            'gofmt -l *' => 'allow',
        ]),
        'rust' => aiPermissionEntries('bash', [
            'cargo build*' => 'allow',
            'cargo check*' => 'allow',
            'cargo test*' => 'allow',
            'cargo clippy*' => 'allow',
            'cargo fmt --check*' => 'allow',
        ]),
        'java' => aiPermissionEntries('bash', [
            './gradlew test*' => 'allow',
            'gradle test*' => 'allow',
            'mvn test*' => 'allow',
            './mvnw test*' => 'allow',
        ]),
        'dotnet' => aiPermissionEntries('bash', [
            'dotnet build*' => 'allow',
            'dotnet test*' => 'allow',
            'dotnet format --verify-no-changes*' => 'allow',
        ]),
        'ruby' => aiPermissionEntries('bash', [
            'bundle exec rspec*' => 'allow',
            'rspec *' => 'allow',
            'rubocop *' => 'allow',
        ]),
        'make' => aiPermissionEntries('bash', [
            'make -n *' => 'allow',
            'make test*' => 'allow',
            'make lint*' => 'allow',
            'make check*' => 'allow',
        ]),
    ];
}
