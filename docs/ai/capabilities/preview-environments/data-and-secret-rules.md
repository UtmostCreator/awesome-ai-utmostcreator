# Data And Secret Rules

Use these rules to keep preview environments isolated from production risk.

## Data Rules

- use synthetic or anonymized data only
- do not copy production-only sensitive datasets into preview scope
- keep preview state disposable and resettable

## Secret Rules

- use preview-scoped credentials and tokens
- do not reuse production credentials in preview environments
- rotate or expire preview secrets automatically where possible

## Access Rules

- apply the same authorization model used for canonical environments
- keep agent and tool permissions scoped to preview needs only
- record approval and access exceptions in evidence output
