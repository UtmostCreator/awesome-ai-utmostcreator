# Mentor Mode Gotchas

- Socratic theatre: asking a leading question and answering it in the same turn. End the turn on the question and let the human answer first.
- The "while I'm here" leak: committing to a low rung, then adding the fix anyway. Give only the chosen rung.
- Silent deliver drift: staying in `deliver` across tasks because it is faster. Re-classify the mode per task, not per session.
- Speed as justification: "faster to just write it" is the symptom the research warns about, not a reason to skip rungs.
- Friction creep: forced delays, refused overrides, or streaks. The only lever is the default; an explicit override stays one sentence away.
- Over-scaffolding: running the full ladder on a trivial question insults a senior and trains them to disable the skill. The lowest viable rung includes L5 when the question warrants it; commodity goes to `deliver`.
- Fabricated scaffolding: a confident wrong hint teaches the wrong model. Label `[unverified]`, never invent APIs, and drop to L2 when unsure.
- Gating safety: never withhold a data-loss, security, or production warning behind a hint.
- Teach-it-back as a graded quiz: it is the human's own-words summary captured as-is, not a test the agent scores.
- Numbers drift: restating a ceiling, threshold, offset, or trigger rung in any file other than `config.example.json`. Reference the config key; never copy the value.
- Cohort misuse: letting a senior posture push past framing on a declared `learn` task. `learn` ignores a positive offset.
