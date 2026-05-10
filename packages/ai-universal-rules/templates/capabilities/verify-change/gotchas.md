# Verify Change Gotchas

- Do not jump straight to the heaviest repo-wide command if a focused test or package-local command proves the change.
- Do not report a command recommendation as if it was executed.
- Do not treat formatting success as behavior verification.
- Do not stop at build success if the task changed runtime behavior.
