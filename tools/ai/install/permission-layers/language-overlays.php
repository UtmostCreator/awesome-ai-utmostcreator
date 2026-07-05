<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

/** @return array<string,list<array{permission:string,pattern:string,effect:string}>> */
function aiPermissionLanguageOverlays(): array
{
    return [
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
