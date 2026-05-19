# Review Diff Checklist

1. Does the diff satisfy the stated task?
2. Is the reported risk level appropriate?
3. Are contracts, schema changes, or public interfaces affected?
4. What failure paths are not covered?
5. Is the verification evidence proportional?
6. Was duplicate-logic screening done before pass?
7. Does changed logic overlap existing patterns by roughly `>=75%` and need reuse or replacement?
8. Is any part of the slice unrelated to the stated outcome?
