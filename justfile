set shell := ["bash", "-cu"]

_default:
  @just --list

bootstrap:
  @just doctor

doctor:
  @bash scripts/doctor.sh

ai-validate-config:
  @php tools/ai/validate-ai-config.php

ai-validate-catalog:
  @php tools/ai/validate-ai-catalog.php

ai-generate-catalog:
  @php tools/ai/generate-ai-catalog.php

repo-structure opts='--with-scc':
  @php tools/ai/generate-repo-structure.php {{opts}}

repo-structure-check opts='--with-scc':
  @php tools/ai/generate-repo-structure.php --check {{opts}}

install-copilot-kit target='.' profile='minimal' opts='--dry-run':
  @bash tools/ai/install-copilot-kit.sh --target {{target}} --profile {{profile}} {{opts}}

install-opencode-kit target='.' profile='opencode' opts='--dry-run':
  @bash tools/ai/install-opencode-kit.sh --target {{target}} --profile {{profile}} {{opts}}

install-ai-kit target='.' profile='dual' runtime='' opts='--dry-run':
  @bash -lc 'runtime="{{runtime}}"; if [[ -n "$runtime" ]]; then runtime="--runtime $runtime"; fi; php tools/ai/install-ai-kit.php --target {{target}} --profile {{profile}} $runtime {{opts}}'

ai-check:
  @php tools/ai/validate-ai-config.php
  @php tools/ai/validate-ai-catalog.php
  @php tools/ai/generate-ai-catalog.php --check

# Surface-integrity gate: validates the shipped agent/adapter/install/doc surfaces.
# Wires the surface validators (config, adapter drift, install surface, agent
# rubric, schemas) plus file-reference integrity into one runnable check.
verify-surface:
  @php tools/ai/validate-ai-config.php
  @php tools/ai/validate-adapter-drift.php
  @php tools/ai/validate-install-surface.php
  @php tools/ai/validate-agent-assessment.php --root=.
  @php tools/ai/validate-agent-assessment-values.php --root=.
  @php tools/ai/validate-schemas.php --root=.
  @bash scripts/ai/bin/verify/check-file-refs.sh

context-analyze path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh analyze "$path" $opts'

context-stats path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh analyze "$path" $opts'

# Print per-file and per-directory line/size metrics. Stdout only, no files written.
repo-info path='.' large_file='500' large_dir='5000':
  @bash scripts/ai/repo-stats.sh {{path}} --large-file {{large_file}} --large-dir {{large_dir}}

# Same as repo-info but scoped to a file extension (e.g. php,blade.php)
repo-info-ext path='.' ext='php':
  @bash scripts/ai/repo-stats.sh {{path}} --ext {{ext}}

# Emit flat per-file JSON for downstream processing
repo-info-json path='.':
  @bash scripts/ai/repo-stats.sh {{path}} --json

# Read-only summary for closeout reporting after context-heavy queries.
query-usage path='.' multiplier='1' label='1x' reserved='4000':
  @bash scripts/ai/query-usage.sh {{path}} --multiplier {{multiplier}} --multiplier-label {{label}} --reserved-output {{reserved}}

# Repomix-aware recursive context planner. Use opts to pass --compress, --context-window, etc.
context-tree-analyze path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh analyze "$path" $opts'

context-tree-plan path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh plan "$path" $opts'

context-tree-pack path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh pack "$path" $opts'

context-tree-all path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh all "$path" $opts'

context-tree-run path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/run-repomix-context.sh "$path" $opts'

context-router-stats path='.' depth='1':
  @bash scripts/ai/repomix-scc-router.sh stats {{path}} --depth {{depth}}

context-plan path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh plan "$path" $opts'

context-router-plan path='.' depth='1' top='25' min_code='300' min_files='2' min_complexity='0':
  @bash scripts/ai/repomix-scc-router.sh plan {{path}} --depth {{depth}} --top {{top}} --min-code {{min_code}} --min-files {{min_files}} --min-complexity {{min_complexity}}

context-plan-since ref path='.' depth='1' top='25' min_code='300' min_files='2' min_complexity='0' churn_count='50':
  @bash scripts/ai/repomix-scc-router.sh plan {{path}} --depth {{depth}} --top {{top}} --min-code {{min_code}} --min-files {{min_files}} --min-complexity {{min_complexity}} --changed-since {{ref}} --churn-count {{churn_count}}

context-pack path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh pack "$path" $opts'

context-router-pack:
  @bash scripts/ai/repomix-scc-router.sh pack .

context-pack-all path='.' opts='--compress --style xml':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh all "$path" $opts'

context-router-pack-all path='.' depth='1' top='25' min_code='300' min_files='2' min_complexity='0':
  @bash scripts/ai/repomix-scc-router.sh all {{path}} --depth {{depth}} --top {{top}} --min-code {{min_code}} --min-files {{min_files}} --min-complexity {{min_complexity}}

context-pack-all-since ref path='.' depth='1' top='25' min_code='300' min_files='2' min_complexity='0' churn_count='50':
  @bash scripts/ai/repomix-scc-router.sh all {{path}} --depth {{depth}} --top {{top}} --min-code {{min_code}} --min-files {{min_files}} --min-complexity {{min_complexity}} --changed-since {{ref}} --churn-count {{churn_count}}

context-clean path='.' opts='':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh clean "$path" $opts'

context-purge path='.' opts='--output-dir .repomix-context':
  @bash -lc 'path="{{path}}"; opts="{{opts}}"; if [[ "$path" == opts=* ]] && [[ -z "$opts" ]]; then opts="${path#opts=}"; path="."; fi; bash scripts/ai/repomix-context-tree.sh purge "$path" $opts'

context-plan-json path='.':
  @bash -lc 'test -f {{path}}/.repomix-context/tree-context/tree-plan.json && cat {{path}}/.repomix-context/tree-context/tree-plan.json || { echo "tree-plan.json not found; run just context-plan or context-pack-all first" >&2; exit 1; }'

search pattern path='.' mode='default':
  @bash scripts/ai/rg-code.sh {{pattern}} {{path}} --mode {{mode}}

agent-search mode query path='.' :
  @bash scripts/ai/ai-search.sh {{mode}} {{query}} {{path}}

verify path='.' :
  @bash scripts/ai/ai-verify.sh {{path}}

edit-ast lang pattern rewrite path='.' :
  @bash scripts/ai/ai-edit.sh ast-grep {{lang}} {{pattern}} {{rewrite}} {{path}}

edit-ast-apply lang pattern rewrite path='.' :
  @APPLY=1 VERIFY=1 bash scripts/ai/ai-edit.sh ast-grep {{lang}} {{pattern}} {{rewrite}} {{path}}

edit-text from to path='.' :
  @bash scripts/ai/ai-edit.sh sd {{from}} {{to}} {{path}}

edit-text-apply from to path='.' :
  @APPLY=1 VERIFY=1 bash scripts/ai/ai-edit.sh sd {{from}} {{to}} {{path}}

search-files query path='.':
  @bash scripts/ai/fd-files.sh {{query}} {{path}}

php-patterns-search query path='reference/php/design-patterns':
  @bash scripts/ai/ai-search.sh text {{query}} {{path}}

php-principles-search query path='reference/php/design-principles':
  @bash scripts/ai/ai-search.sh text {{query}} {{path}}

php-builtins-search query path='reference/php/php-built-ins':
  @bash scripts/ai/ai-search.sh text {{query}} {{path}}

php-examples-map:
  @printf '%s\n' 'PHP example lookup order:' '1) reference/php/design-patterns (primary)' '2) reference/php/design-principles (secondary)' '3) reference/php/php-built-ins (supporting)'

context-since ref:
  @bash scripts/ai/ai-diff-context.sh since {{ref}}

diff-stat base head='HEAD':
  @bash -lc 'base="{{base}}"; head="{{head}}"; merge_base="$(git merge-base "$base" "$head")" && git diff --stat "$merge_base..$head"'

context-unstaged:
  @bash scripts/ai/ai-diff-context.sh unstaged

context-pr pr:
  @bash scripts/ai/ai-diff-context.sh pr {{pr}}

context-recent count='10':
  @bash scripts/ai/ai-diff-context.sh recent --count {{count}}

context-touched pattern:
  @bash scripts/ai/ai-diff-context.sh touched {{pattern}}

pr-meta pr:
  @bash scripts/ai/gh-pr-context.sh {{pr}}

pr-review pr:
  @bash scripts/ai/gh-pr-context.sh {{pr}} --diff --checks --reviews

rollback-list:
  @bash scripts/ai/ai-rollback.sh list

rollback-show target:
  @bash scripts/ai/ai-rollback.sh show {{target}}

rollback target:
  @bash scripts/ai/ai-rollback.sh apply {{target}}

hook-run-precommit:
  @bash scripts/hooks/pre-commit.sh

hook-run-commitmsg msg:
  @bash scripts/hooks/commit-msg.sh {{msg}}

# Run the repo secret scanner on request. Gitleaks is OFF BY DEFAULT elsewhere
# (fast local/test runs); this recipe and release contexts (AI_RELEASE=1) opt in.
secret-scan:
  @php tools/ai/secret-scan.php --run

secret-scan-gitleaks:
  @gitleaks protect --staged --redact --verbose

secret-scan-trufflehog:
  @trufflehog git file://. --since-commit HEAD --results=verified,unknown --fail

health-check mode='full':
  @bash scripts/repo-health-check.sh {{mode}}

# List files not referenced anywhere in the repository (surfaces orphaned docs and unused assets).
check-refs path='.' opts='':
  @bash scripts/ai/check-file-refs.sh {{opts}} {{path}}

# Same as check-refs but emit JSON output for downstream processing.
check-refs-json path='.':
  @bash scripts/ai/check-file-refs.sh --format json {{path}}

lint:
  @shellcheck -x $(git ls-files '*.sh')
  @shfmt -d $(git ls-files '*.sh')
  @actionlint
  @bash scripts/run-link-check.sh

test-php:
  @composer install --no-interaction --prefer-dist
  @vendor/bin/phpunit --colors=never

test-shell:
  @LC_ALL=C LANG=C TZ=UTC bats tests/shell/

test: test-php test-shell

ci: ai-check lint test
