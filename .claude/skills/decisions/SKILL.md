---
name: decisions
description: Approaches this repo already tried and rejected, with the evidence and what would change the answer. Read before proposing a mago/CI rule to enforce a convention, before adding a dependency, before changing how paths or @throws are declared, and any time the thought is "why doesn't this just do X" — X has often been tried. Add an entry when a change is abandoned for a reason a future reader would not rediscover.
---

The record lives in `docs/decisions.md`, not here — it is documentation for anyone working on the
repository, not agent configuration. Read that file now.

When abandoning a change for a reason that will not be obvious later, add an entry there in the
shape the others use: what was attempted, what actually happened, and what would have to change for
the answer to be different. A verdict without its evidence gets re-litigated.
