# AGENTS.md

This file provides guidance to coding agents when working with code in this repository.

Akeeba Amazon S3 Connector (`akeeba/s3`) is a compact, dependency-less PHP library (PHP 7.4 or later,
`ext-curl`, `ext-simplexml`) implementing the most commonly used parts of the Amazon S3 API: listing buckets
and bucket contents, single and multipart uploads, downloads, deletes, existence checks and presigned URLs.
It works with Amazon S3 proper and with S3-compatible services. Code lives under `src/` in the `Akeeba\S3`
namespace (`src/aliasing.php` provides the legacy-namespace aliases); see `README.md` for usage and the
supported configuration options.

## Git: commit and tag outside the sandbox

Commits and tags are always signed, with a key held in 1Password. The 1Password signing agent is reached
over a local socket that agent sandboxes do not expose, so a sandboxed `git commit` or `git tag` **always**
fails (e.g. `error: 1Password: Could not connect to socket. Is the agent running?`).

Run every `git commit` and `git tag` **outside the sandbox from the first attempt** — in Claude Code with
`dangerouslyDisableSandbox: true`, in other harnesses with their equivalent unsandboxed / escalated
execution. Do not try the sandboxed form first, do not diagnose the failure, and never work around it
with `--no-gpg-sign`, `-c commit.gpgsign=false` or unsigned tags.

## Project memory

Project memory lives in `.claude/memory/`, committed with the code, so that it is shared across machines
and across agentic harnesses (Claude Code, Codex, Qwen Code, Kimi Code, Junie, …). There are no memory
files yet. When there is something worth remembering, create `.claude/memory/`, the topic file, and a
table here mapping each file to a concrete trigger ("Before you… | Read").

### Recording new memories

This is the **default and only** place for project memory. Do not write memories for this project to a
harness's private memory store (such as Claude Code's auto-memory under `~/.claude/projects/`); write
them here instead:

- Add to the existing topic file when one fits; otherwise create a new kebab-case `.md` file named after
  the topic, and add a row for it to the table above with a concrete trigger.
- Plain Markdown, no frontmatter. State the rule, then **Why:** (the reason or incident behind it) and
  **How to apply:**. Link related files with relative Markdown links.
- Don't record what the code, Git history or an existing `AGENTS.md` already says — update that
  `AGENTS.md` instead when the rule belongs there. Remove or correct entries that turn out wrong.
- These files are committed: no secrets, credentials, customer data or personal details.
