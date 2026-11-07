# specs/

One folder per feature, `<id>-<slug>/` (e.g. `rnc-12-sidebar-menu/`): `intent.md`, `facts.md`,
`spec.md`, `plan.md`, `state.md`, `proposal.html`. The flow, the file formats and the ID rule are in
[`.claude/feature-flow.md`](../.claude/feature-flow.md).

Versioned on purpose: the folder travels with the feature's branch. **Invented examples only** —
never a real person's name, record or contact.

Where every feature stands:

```bash
grep -H '^Stage:' specs/*/state.md
```
