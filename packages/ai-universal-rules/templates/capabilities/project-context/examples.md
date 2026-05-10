# Project Context Examples

## Example: Web App With API And Worker

If a request touches checkout behavior:

- read active paths for API, web, and worker ownership
- identify the API contract and async side effects
- route verification toward focused API tests first, then worker or UI checks only if behavior crosses those boundaries

## Example: Monorepo

If a request adds a field consumed by multiple packages:

- identify the owning package for the source contract
- list downstream consumers
- flag release coordination and compatibility risk before implementation
