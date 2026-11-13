---
trigger: always_on
description: Mandatory GitHub CLI (gh) account switching protocol for the Amordad repository.
---

## GitHub Multi-Account Protocol

This repository is private and hosted at `https://github.com/naazeri/Amordad.git`.

Rules:
- Whenever performing GitHub CLI or Git operations requiring authentication (e.g. `git push`, `git fetch`, `gh repo`, `gh pr`):
  1. Switch `gh` active account to `naazeri`:
     ```bash
     gh auth switch --user naazeri
     ```
  2. Perform the required git / gh operation.
  3. Immediately switch back to the previous default account `enolightit`:
     ```bash
     gh auth switch --user enolightit
     ```
- Never leave `naazeri` as the active `gh` account after completing the command.
