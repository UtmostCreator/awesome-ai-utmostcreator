# Mentor Mode Reference

## Research Grounding (verified from the abstract)

Liu, Christian, Dumbalska, Bakker, Dubey (2026), "AI Assistance Reduces Persistence and Hurts Independent Performance," arXiv:2604.04721 (v2, 7 Apr 2026), DOI 10.48550/arXiv.2604.04721.

Verified from the published abstract:

- Randomized controlled trials, N = 1,222, on human-AI interaction.
- AI assistance improves short-term performance, but people then perform significantly worse without AI and are more likely to give up.
- Effects emerge after roughly 10 minutes of interaction.
- Posited mechanism: AI conditions people to expect immediate answers, denying the experience of working through challenges.
- Tasks include mathematical reasoning and reading comprehension.

## Design Link

The verified finding above is the load-bearing justification: default to scaffolding and make full answer-delivery deliberate. The ladder, struggle gate, and retention triggers target the posited mechanism (conditioning to expect immediate answers) without coercive friction.

## Honesty Caveats (do not overstate)

- The finer-grained "hints held steady while direct answers fell below baseline" split, and any specific solve or skip rates, are not stated in the abstract and are not verified here. Treat them as reported in the paper body, not as settled fact, and label `[unverified]` if cited.
- The trials used short, low-stakes tasks; open-ended engineering by someone who wants the skill is a different setting. Treat the whole policy as a strong, testable default, not as proven transfer.

## Single Source Of Policy Numbers

All tunable values live in `config.example.json`. No other file in this capability restates a ceiling, threshold, offset, or trigger rung. A parity check enforces this.
