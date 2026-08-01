---
name: architecture
description: The compile pipeline, runtime resolution, and extension points of ray-di-context. Use when tracing how a compile flows through CompileRunner, when changing the order or contents of a pipeline step, when implementing or replacing one of the package's interfaces, or when a change to src/ needs to know why a step sits where it does.
---

The description lives in `docs/architecture.md`, not here — it is documentation for anyone working
on the repository, not agent configuration. Read that file now.

Keep it current when a pipeline step, an interface or the order of `CompileRunner::run()` changes.
It is the stated home for *why* the code is shaped as it is, so a docblock repeating it is a second
copy free to drift.
