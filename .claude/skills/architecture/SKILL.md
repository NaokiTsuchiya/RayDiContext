---
name: architecture
description: How ray-di-context works inside — the compile pipeline, runtime resolution, extension points, and the filesystem behaviour the guards are built around. Use when tracing how a compile flows through CompileRunner, when changing the order or contents of a pipeline step, when implementing or replacing one of the package's interfaces, when a change to src/ needs to know why a step sits where it does, and when a directory mode, permission bit or FilesystemIterator failure is involved.
---

Read `docs/architecture.md`. Keep it current when a pipeline step, an interface, or the order of
`CompileRunner::run()` changes — it is the stated home for *why* the code is shaped as it is, so a
docblock repeating it is a second copy free to drift.
