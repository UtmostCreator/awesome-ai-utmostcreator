# Dependency Upgrade Examples

## Example: Framework Minor Upgrade

- Scope: web app framework from one minor version to the next
- Compatibility risk: medium because routing and build hooks are touched
- Verification: focused app tests, then app build, then targeted smoke flow

## Example: Formatter Upgrade

- Scope: code formatter only
- Compatibility risk: low because runtime behavior is unchanged
- Verification: config validation and representative formatting run
