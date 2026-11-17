# Agent guidance

This folder contains task-specific agent guidance for this repository. Use the
matching file as context for its domain; these files supplement rather than
replace repository and scoped Copilot instructions.

The authoritative project instructions are:

- `AGENTS.md` — repo-level operational rules and current stack summary
- `.github/copilot-instructions.md` — repo-wide Copilot summary
- `.github/instructions/*.instructions.md` — scoped, GitHub Copilot-recognized instruction bundles

Copilot CLI also supports selecting custom agents with `/agent` or `--agent`.
Do not assume these Markdown guidance files are registered as selectable
Copilot agents; use them as references unless the current environment confirms
otherwise.

For this repository, the current stack is:

- Laravel 12 / PHP 8.3
- React 18 + Inertia v2 + MUI v7 + Tailwind 3
- Vite 6
- MySQL 8
- `stancl/tenancy` v3 multi-tenancy

Treat any stale `.opencode/agents/*` references as historical. Verify paths and
versions against `AGENTS.md` and the actual project configuration.
