# Release Safety Gotchas

- Do not treat passing tests as a complete release strategy.
- Do not assume a feature flag exists unless the repository actually has one.
- Do not describe rollback as available if data or contract changes make it impractical.
- Do not skip post-release observation for risky migrations or shared contract changes.
