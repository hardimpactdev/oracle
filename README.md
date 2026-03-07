# Oracle CLI

Project-aware AI tool for planning, reviewing, verifying, and learning from code changes. Built with Laravel Zero.

## Commands

### `dexter plan`

Generate a structured implementation plan from a task description.

```bash
dexter plan "Add dark mode support" --project /path/to/project --json
dexter plan --file task.json --project /path/to/project --json
```

### `dexter review`

Review code changes against project conventions.

```bash
dexter review --branch feature/thing --project /path/to/project --json
dexter review --pr 42 --project /path/to/project --detect-workarounds --json
```

### `dexter verify`

Verify coder work against task requirements and beads completion.

```bash
dexter verify \
  --transcript-file /tmp/transcript.json \
  --task-file /tmp/task.json \
  --beads-status '{"total": 5, "completed": 5}' \
  --solutions-index docs/solutions/INDEX.yaml \
  --project /path/to/worktree \
  --json
```

**Output:**
```json
{
  "success": true,
  "data": {
    "verdict": "pass|follow_up|package_issue|fail",
    "summary": "...",
    "follow_up_question": "...",
    "package_issues": [{"package": "...", "description": "...", "severity": "...", "blocking": false}],
    "confidence": 0.85
  }
}
```

**Verdicts:**

| Verdict | Meaning |
|---------|---------|
| `pass` | All beads done, implementation matches requirements |
| `follow_up` | Need clarification from coder before deciding |
| `package_issue` | Work passes but a managed package has a gap |
| `fail` | Fundamentally wrong approach |

### `dexter ask`

Ask a question about the project.

```bash
dexter ask "How does authentication work?" --project /path/to/project --json
```

### `dexter learn`

Extract learnings from code changes or reviews.

```bash
dexter learn --source "PR #123" --content "diff..." --project /path/to/project --json
```

## Common Options

All commands support:

| Option | Description |
|--------|-------------|
| `--project` | Path to the project (defaults to cwd) |
| `--driver` | LLM driver: `gemini`, `claude`, `codex` |
| `--model` | LLM model to use |
| `--json` | Output as JSON (wrapped in `{success, data}` envelope) |

## Context Gathering

Oracle automatically gathers project context:

- **Conventions** from `AGENTS.md` / `CLAUDE.md`
- **Solution docs** from `docs/solutions/`
- **Hierarchical docs** from `CLAUDE.md` files in subdirectories
- **Memories** from the Recall MCP server (when available)

## Configuration

Oracle uses a hierarchical config system:

1. Global config (`~/.config/dexter/config.json`)
2. Project config (`.dexter.json` in project root)
3. CLI flags (highest priority)

## Development

```bash
composer install
vendor/bin/pest          # Run tests
vendor/bin/pint          # Format code
php dexter list          # List all commands
```
