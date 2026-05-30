# Lifecycle

Use this lifecycle for vendor-neutral preview environments.

## Flow

`create -> seed -> verify -> use -> observe -> destroy`

## Required Controls

- deterministic environment ID such as `pr-123` or `preview-task-456`
- explicit expiry or TTL for every preview environment
- mandatory destroy step after review, merge, or TTL expiry
- cleanup verification recorded in task evidence

## Recommended Evidence Fields

- `environment_id`
- `lifecycle_state`
- `ttl`
- `created_at`
- `expires_at`
- `destroyed_at`
