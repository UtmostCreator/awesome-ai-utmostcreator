## Verdict

The strongest production-ready refactor is **not** merely extracting helpers such as `file()` and `dir()`.

Use a **normalized artifact catalog + pack memberships + validated compiler + generated runtime registry**.

| Design                               |      Score | Limitation                                           |
| ------------------------------------ | ---------: | ---------------------------------------------------- |
| Current monolithic function          |     42/100 | Explicit but extremely fragile                       |
| Helper functions returning arrays    |     61/100 | Less repetition, same structural risks               |
| Separate PHP file per pack           |     76/100 | Better ownership, duplicates remain                  |
| Typed pack providers                 |     85/100 | Strong, but adds runtime bootstrapping               |
| **Catalog + memberships + compiler** | **94/100** | Best safety, maintainability, and runtime simplicity |

A 90+ benchmark means: one source of truth per artifact, validated collisions, typed options, deterministic generated output, compatibility with the current executor, and CI drift detection.

## Main defects in the current implementation

### 1. Artifact definitions are duplicated

Examples include:

- `PLACEHOLDERS.md`
- `.ai/placeholders.json`
- `.ai/kit-manifest.json`
- `.ai/catalog.json`
- `docs/ai/failure-handling.md`
- `docs/ai/validation.md`
- `docs/ai/hooks.md`

The same artifact can have different `required` values depending on where it appears. Consequently, artifact metadata can silently diverge.

An artifact should be defined once. Packs should reference it.

### 2. Target collisions are implicit

`adapter-opencode` writes two directories to the same target:

```php
'.opencode/commands'
```

Both use:

```php
'merge_strategy' => 'replace'
```

Whether this works depends on undocumented executor behaviour. If directory replacement removes the target before copying, the second entry can erase the first one.

The registry must explicitly distinguish:

- replace target
- preserve existing target
- overlay into target

### 3. Pack membership and artifact metadata are mixed

These are separate concepts:

```php
'source'
'target'
'type'
'install_type'
```

describe the artifact.

```php
'required'
```

usually describes the artifact’s role within a selected pack.

Combining both causes duplicated definitions whenever one artifact belongs to multiple packs.

### 4. The runtime function contains authoring history

Ticket references and historical explanations are valuable, but they should live in:

- an ADR
- a ticket
- artifact rationale metadata
- focused comments beside the relevant catalog group

They make the executable registry difficult to review and obscure current invariants.

### 5. Dependency closure is manually maintained

`$targetToolInstallerFiles` has repeatedly required fixes after extracted files were omitted.

That means the registry is acting as a manually maintained dependency resolver. Either:

1. install the complete runtime directory, or
2. generate and validate the dependency closure automatically.

### 6. There is no central validation boundary

Nothing in this function prevents:

- absolute target paths
- `../` traversal
- duplicate pack entries
- incompatible duplicate targets
- `merge_into_existing` on files
- `rename_ext` on files
- unknown `install_type`
- missing required source files
- non-deterministic overlay order

## Recommended architecture

```text
tools/ai/install/registry/
├── Artifact.php
├── ArtifactCatalog.php
├── PackDefinition.php
├── PackItem.php
├── RegistryCompiler.php
├── RegistryValidator.php
├── catalog/
│   ├── docs.php
│   ├── installer-runtime.php
│   ├── scripts.php
│   ├── schemas.php
│   ├── adapters.php
│   └── capabilities.php
├── packs/
│   ├── setup-docs.php
│   ├── base.php
│   ├── adapter-copilot.php
│   ├── adapter-opencode.php
│   ├── adapter-claude.php
│   ├── scripts-pack.php
│   └── target-tools-pack.php
└── generated/
    └── pack-registry.php
```

The existing public function becomes a trivial compatibility boundary:

```php
function aiInstallerPackRegistry(): array
{
    static $registry;

    if ($registry === null) {
        /** @var array<string, list<array<string, mixed>>> $loaded */
        $loaded = require __DIR__ . '/registry/generated/pack-registry.php';
        $registry = $loaded;
    }

    return $registry;
}
```

The generated file remains a plain PHP array, so installed targets do not need the authoring classes, autoloading, YAML parsing, or additional dependencies.

---

## 1. Typed artifact model

```php
<?php

declare(strict_types=1);

namespace AiInstaller\Registry;

enum ArtifactType: string
{
    case File = 'file';
    case Directory = 'dir';
}

enum Ownership: string
{
    case Core = 'core';
    case Pack = 'pack';
}

enum WriteMode: string
{
    case Replace = 'replace';
    case PreserveExisting = 'preserve-existing';
    case Overlay = 'overlay';
}

enum InstallType: string
{
    case CopilotAgents = 'copilot-agents';
    case OpenCodeAgents = 'opencode-agents';
    case ClaudeAgents = 'claude-agents';
    case SkillDirectories = 'skill-dirs';
    case OpenCodeCommands = 'opencode-commands';
    case ClaudeSettingsMerge = 'claude-settings-merge';
}

final class Artifact
{
    public function __construct(
        public readonly string $id,
        public readonly ArtifactType $type,
        public readonly string $source,
        public readonly string $target,
        public readonly Ownership $ownership = Ownership::Pack,
        public readonly WriteMode $writeMode = WriteMode::Replace,
        public readonly ?InstallType $installType = null,
        public readonly ?string $renameExtension = null,
        public readonly bool $neverAutoMerge = false,
    ) {
        $this->assertValid();
    }

    public static function mirrorFile(
        string $id,
        string $path,
        Ownership $ownership = Ownership::Pack,
        WriteMode $writeMode = WriteMode::Replace,
    ): self {
        return new self(
            id: $id,
            type: ArtifactType::File,
            source: $path,
            target: $path,
            ownership: $ownership,
            writeMode: $writeMode,
        );
    }

    public static function file(
        string $id,
        string $source,
        string $target,
        Ownership $ownership = Ownership::Pack,
        WriteMode $writeMode = WriteMode::Replace,
        ?InstallType $installType = null,
        bool $neverAutoMerge = false,
    ): self {
        return new self(
            id: $id,
            type: ArtifactType::File,
            source: $source,
            target: $target,
            ownership: $ownership,
            writeMode: $writeMode,
            installType: $installType,
            neverAutoMerge: $neverAutoMerge,
        );
    }

    public static function directory(
        string $id,
        string $source,
        string $target,
        Ownership $ownership = Ownership::Pack,
        WriteMode $writeMode = WriteMode::Replace,
        ?InstallType $installType = null,
        ?string $renameExtension = null,
    ): self {
        return new self(
            id: $id,
            type: ArtifactType::Directory,
            source: $source,
            target: $target,
            ownership: $ownership,
            writeMode: $writeMode,
            installType: $installType,
            renameExtension: $renameExtension,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toLegacyArray(bool $required): array
    {
        $entry = [
            'type' => $this->type->value,
            'source' => $this->source,
            'target' => $this->target,
            'core' => $this->ownership === Ownership::Core,
            'merge_strategy' => match ($this->writeMode) {
                WriteMode::PreserveExisting => 'skip-if-exists',
                WriteMode::Replace,
                WriteMode::Overlay => 'replace',
            },
            'required' => $required,
        ];

        if ($this->installType !== null) {
            $entry['install_type'] = $this->installType->value;
        }

        if ($this->renameExtension !== null) {
            $entry['rename_ext'] = $this->renameExtension;
        }

        if ($this->writeMode === WriteMode::Overlay) {
            $entry['merge_into_existing'] = true;
        }

        if ($this->neverAutoMerge) {
            $entry['never_auto_merge'] = true;
        }

        return $entry;
    }

    private function assertValid(): void
    {
        if ($this->id === '') {
            throw new \InvalidArgumentException('Artifact ID cannot be empty.');
        }

        self::assertRelativePath($this->source, 'source');
        self::assertRelativePath($this->target, 'target');

        if (
            $this->type !== ArtifactType::Directory
            && $this->writeMode === WriteMode::Overlay
        ) {
            throw new \InvalidArgumentException(
                "Artifact {$this->id}: overlay mode requires a directory."
            );
        }

        if (
            $this->type !== ArtifactType::Directory
            && $this->renameExtension !== null
        ) {
            throw new \InvalidArgumentException(
                "Artifact {$this->id}: renameExtension requires a directory."
            );
        }
    }

    private static function assertRelativePath(string $path, string $field): void
    {
        $normalized = str_replace('\\', '/', $path);

        if ($normalized === '') {
            throw new \InvalidArgumentException("$field path cannot be empty.");
        }

        if (
            str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || preg_match('~(?:^|/)\.\.(?:/|$)~', $normalized) === 1
        ) {
            throw new \InvalidArgumentException(
                "$field must be a safe repository-relative path: $path"
            );
        }
    }
}
```

`WriteMode::Overlay` replaces the ambiguous combination of:

```php
'merge_strategy' => 'replace',
'merge_into_existing' => true,
```

while still compiling to the legacy representation.

---

## 2. Define artifacts once

```php
<?php

declare(strict_types=1);

use AiInstaller\Registry\Artifact;
use AiInstaller\Registry\ArtifactCatalog;
use AiInstaller\Registry\Ownership;
use AiInstaller\Registry\WriteMode;

return static function (ArtifactCatalog $catalog): void {
    $catalog->add(
        Artifact::file(
            id: 'kit.placeholders-documentation',
            source: 'packages/ai-universal-rules/PLACEHOLDERS.md',
            target: 'PLACEHOLDERS.md',
        )
    );

    $catalog->add(
        Artifact::file(
            id: 'kit.placeholders-config',
            source: 'packages/ai-universal-rules/placeholders.json',
            target: '.ai/placeholders.json',
        )
    );

    $catalog->add(
        Artifact::file(
            id: 'docs.failure-handling',
            source: 'docs/ai/failure-handling.md',
            target: 'docs/ai/failure-handling.md',
            writeMode: WriteMode::PreserveExisting,
        )
    );

    $catalog->add(
        Artifact::file(
            id: 'core.agents',
            source: 'packages/ai-universal-rules/templates/core/AGENTS.template.md',
            target: 'AGENTS.md',
            ownership: Ownership::Core,
        )
    );
};
```

Now `docs.failure-handling.md` cannot accidentally acquire a different source, target, ownership, or write policy in another pack.

---

## 3. Keep `required` on pack membership

```php
<?php

declare(strict_types=1);

namespace AiInstaller\Registry;

final class PackItem
{
    private function __construct(
        public readonly string $artifactId,
        public readonly bool $required,
    ) {
        if ($artifactId === '') {
            throw new \InvalidArgumentException(
                'Pack artifact ID cannot be empty.'
            );
        }
    }

    public static function required(string $artifactId): self
    {
        return new self($artifactId, true);
    }

    public static function optional(string $artifactId): self
    {
        return new self($artifactId, false);
    }
}

final class PackDefinition
{
    /**
     * @param non-empty-string $name
     * @param list<PackItem>    $items
     */
    public function __construct(
        public readonly string $name,
        public readonly array $items,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Pack name cannot be empty.');
        }
    }
}
```

A shared artifact can therefore be required in one pack and optional in another without duplicating its invariant metadata.

```php
<?php

declare(strict_types=1);

use AiInstaller\Registry\PackDefinition;
use AiInstaller\Registry\PackItem;

return new PackDefinition(
    name: 'policy-pack',
    items: [
        PackItem::required('docs.command-risk-taxonomy'),
        PackItem::required('docs.failure-handling'),
        PackItem::required('schema.evidence-event'),
    ],
);
```

```php
<?php

declare(strict_types=1);

use AiInstaller\Registry\PackDefinition;
use AiInstaller\Registry\PackItem;

return new PackDefinition(
    name: 'docs-reference-pack',
    items: [
        PackItem::optional('docs.agent-ops'),
        PackItem::optional('docs.failure-handling'),
        PackItem::optional('docs.validation'),
        PackItem::optional('docs.hooks'),
    ],
);
```

---

## 4. Use groups for true dependency closures

The installer runtime list should be declared as a reusable catalog group:

```php
<?php

declare(strict_types=1);

use AiInstaller\Registry\Artifact;
use AiInstaller\Registry\ArtifactCatalog;

return static function (ArtifactCatalog $catalog): void {
    $paths = [
        'tools/ai/install/backup.php',
        'tools/ai/install/base.sh',
        'tools/ai/install/canonical-agent-frontmatter.php',
        'tools/ai/install/claude-agent-renderer.php',
        'tools/ai/install/claude-agent-tool-registry.php',
        'tools/ai/install/claude-settings-merge.php',
        'tools/ai/install/config.php',
        'tools/ai/install/conflict-channels.php',
        'tools/ai/install/copilot-agent-handoff-registry.php',
        'tools/ai/install/copilot-agent-renderer.php',
        'tools/ai/install/copilot-agent-tool-registry.php',
        'tools/ai/install/core.php',
        'tools/ai/install/docs.php',
        'tools/ai/install/executor.php',
        'tools/ai/install/fs-writers.php',
        'tools/ai/install/generated-header.php',
        'tools/ai/install/gitignore.php',
        'tools/ai/install/install-lock.php',
        'tools/ai/install/lib.sh',
        'tools/ai/install/manifest.php',
        'tools/ai/install/markers.php',
        'tools/ai/install/migrations.php',
        'tools/ai/install/packs.php',
        'tools/ai/install/placeholders.php',
        'tools/ai/install/plan-guards.php',
        'tools/ai/install/planner.php',
        'tools/ai/install/profiles.php',
        'tools/ai/install/project-values.php',
        'tools/ai/install/project-yaml.php',
        'tools/ai/install/runtime-copilot.sh',
        'tools/ai/install/runtime-opencode.sh',
        'tools/ai/install/script-registry.php',
        'tools/ai/install/script-runner.php',
        'tools/ai/install/selection-engine.php',
        'tools/ai/install/stack-detection.php',
        'tools/ai/install/stack-project-doc.php',
        'tools/ai/install/stack-registry.php',
        'tools/ai/install/toolchain.php',
        'tools/ai/install/toolchain-registry.php',
        'tools/ai/install/upgrade-file-actions.php',
        'tools/ai/install/workflow-manifest.php',
        'tools/ai/install/uninstall-prune.php',
        'tools/ai/install/restore-audit.php',
        'tools/ai/install/user-sections.php',
        'tools/ai/install/verify-install-result.php',
        'tools/ai/install/verify-manifest-args.php',
        'tools/ai/install/verify-manifest.php',
        'tools/ai/install/verify-no-overwrite.php',
        'tools/ai/compile-command-policy.php',
    ];

    foreach ($paths as $path) {
        $catalog->add(
            Artifact::mirrorFile(
                id: 'installer-runtime:' . $path,
                path: $path,
            )
        );
    }
};
```

The pack references those IDs instead of reconstructing arrays.

Long term, the stronger filesystem structure is:

```text
tools/ai/install/runtime/
tools/ai/install/authoring/
tools/ai/install/tests/
```

Then the entire runtime closure becomes one directory artifact rather than approximately 50 manually synchronized files.

---

## 5. Make overlays explicit

The OpenCode command destinations should be modelled as ordered overlays:

```php
$catalog->add(
    Artifact::directory(
        id: 'opencode.commands.workflows',
        source: 'packages/ai-universal-rules/templates/workflows',
        target: '.opencode/commands',
        writeMode: WriteMode::Overlay,
        installType: InstallType::OpenCodeCommands,
    )
);

$catalog->add(
    Artifact::directory(
        id: 'opencode.commands.standalone',
        source: 'packages/ai-universal-rules/templates/commands',
        target: '.opencode/commands',
        writeMode: WriteMode::Overlay,
        installType: InstallType::OpenCodeCommands,
    )
);
```

The validator should reject two directory artifacts with the same target unless all involved entries explicitly use `Overlay`.

The executor should also apply overlay entries without deleting the destination directory.

---

## 6. Compiler

```php
<?php

declare(strict_types=1);

namespace AiInstaller\Registry;

final class RegistryCompiler
{
    public function __construct(
        private readonly ArtifactCatalog $catalog,
        private readonly RegistryValidator $validator,
    ) {
    }

    /**
     * @param list<PackDefinition> $packs
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function compile(array $packs): array
    {
        $this->validator->validate($this->catalog, $packs);

        $compiled = [];

        foreach ($packs as $pack) {
            $entries = [];

            foreach ($pack->items as $item) {
                $artifact = $this->catalog->get($item->artifactId);
                $entries[] = $artifact->toLegacyArray($item->required);
            }

            $compiled[$pack->name] = $entries;
        }

        return $compiled;
    }
}
```

The release compiler writes deterministic PHP:

```php
$content = <<<'PHP'
<?php

declare(strict_types=1);

// GENERATED FILE. DO NOT EDIT.
// Run: php tools/ai/install/compile-pack-registry.php

return %s;
PHP;

$output = sprintf($content, var_export($compiledRegistry, true));

file_put_contents(
    __DIR__ . '/registry/generated/pack-registry.php',
    $output . PHP_EOL,
);
```

Do not sort entries unless ordering is formally declared irrelevant. Preserve pack declaration order because overlay execution can be order-sensitive.

---

## Required validator rules

`RegistryValidator` should enforce at least these rules:

1. Artifact IDs are globally unique.
2. Pack names are globally unique.
3. Pack memberships do not contain duplicate artifact IDs.
4. Every referenced artifact exists.
5. Source and target paths are repository-relative and traversal-free.
6. Required source artifacts exist during compilation.
7. Exact target collisions are rejected unless:
   - they reference the same canonical artifact, or
   - every colliding directory entry explicitly uses overlay mode.

8. File targets cannot use overlay mode.
9. `rename_ext` applies only to directory transformations.
10. `merge_into_existing` is emitted only for overlay operations.
11. Parent-directory replacement cannot run after a child overlay.
12. `install_type` is valid for the artifact type.
13. Generated output is deterministic.
14. Pack selection cannot produce incompatible operations for one target.
15. Case-insensitive target collisions are detected for cross-platform installations.

The last rule should detect conflicts such as:

```text
docs/AI/File.md
docs/ai/file.md
```

even when developing on a case-sensitive filesystem.

## Generated-file check

CI should run:

```bash
php tools/ai/install/compile-pack-registry.php --check
```

`--check` should compile into memory and compare it with the committed generated file. It must fail when they differ.

Recommended verification sequence:

```bash
php tools/ai/install/compile-pack-registry.php --check
php tools/ai/validate-install-surface.php
php tools/ai/verify-full-install.php
php tools/ai/full-install-validation.php
```

Also test these lifecycle operations against temporary target repositories:

```text
fresh install
repeat install
upgrade
dry run
uninstall
restore
dual-runtime install
single-runtime install
all-features install
optional-agent overlays
```

## Specific clean-ups in the supplied registry

### Remove duplicate Copilot instruction entries

This directory entry:

```php
[
    'source' => 'packages/ai-universal-rules/templates/instructions',
    'target' => '.github/instructions',
]
```

appears to include these separately declared files:

```php
tools.instructions.md
execution-protocol.instructions.md
```

Keep either:

- the directory artifact only, or
- individual artifact definitions only.

Do not copy both unless the individual files receive a distinct transformation.

### Normalize package metadata

Define these once:

```text
kit.placeholders-documentation
kit.placeholders-config
kit.manifest-json
kit.manifest-yaml
kit.package-lock
kit.catalog
kit.package-docs
kit.policies
```

Then reference them from:

```text
setup-docs
target-tools-pack
package-source-pack
```

### Normalize shared documentation

Define these once and reference them from multiple packs:

```text
docs.failure-handling
docs.validation
docs.hooks
```

### Move historical comments

Replace long ticket comments with concise current rationale:

```php
// Required runtime dependency of core.php.
```

Keep ticket history in an ADR such as:

```text
docs/architecture/installer-runtime-surface.md
```

### Replace boolean `core`

Use an enum or explicit ownership classification:

```php
Ownership::Core
Ownership::Pack
```

A boolean does not explain what `false` means.

### Rename `required`

Internally use:

```php
requiredWhenSelected
```

The legacy compiler can continue emitting:

```php
'required' => true
```

This removes ambiguity between “source file must exist” and “artifact is mandatory when this pack is selected.”

## Migration order

1. Snapshot the exact current registry output.
2. Add typed artifact and pack classes without changing executor behaviour.
3. Import every current entry into the catalog.
4. Model duplicated entries as shared artifact memberships.
5. Add collision validation in report-only mode.
6. Resolve `.opencode/commands` and other directory overlays explicitly.
7. Generate the legacy registry.
8. Replace the monolithic function with the generated-file loader.
9. Make registry compilation and validation mandatory in CI.
10. Split installable runtime files from authoring-only files, allowing directory-level installation.

The critical design decision is:

> **An artifact is defined exactly once; packs only reference artifacts.**

That change removes the largest source of drift while retaining the exact array contract expected by the existing installer.
