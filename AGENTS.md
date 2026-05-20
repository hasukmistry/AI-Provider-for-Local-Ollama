# AGENTS.md

This file is the operating playbook for future agent sessions working on this repository.

## Repository Snapshot

- Project: **AI Provider for Local Ollama**
- Type: WordPress plugin
- Primary goal: improve adoption and trust for a **WordPress Ollama / Local AI / Self-hosted AI** provider plugin.
- Current code footprint is small:
  - `ai-provider-for-local-ollama.php`
  - `includes/class-ollama-provider.php`
  - `includes/class-ollama-model-metadata-directory.php`
  - `includes/class-ollama-text-generation-model.php`
  - `includes/admin-connectors-ui.js`
  - `README.md`

## North Star

Optimize for:

1. Discoverability (search + GitHub browse)
2. Credibility (clear docs, compatibility, tests/CI, security posture)
3. Adoption (fast setup, examples, troubleshooting)
4. Shareability (visual assets + launch-ready content)

Do this **without breaking plugin functionality**.

## Execution Order (Default)

Use this order unless the user requests otherwise.

1. **Baseline audit**
   - Validate current plugin behavior still works.
   - Review docs, folder structure, and missing community files.
2. **README conversion optimization**
   - Rewrite for visitor-to-star conversion in first 10 seconds.
   - Keep claims technical and verifiable.
3. **GitHub metadata and community health**
   - Add templates, governance docs, release/changelog structure.
4. **DX and onboarding**
   - Add setup guides, local dev guide, troubleshooting depth.
5. **Automation and quality signals**
   - Add lightweight CI (markdown lint, link checks, basic PHP checks if possible).
6. **Demo and growth assets**
   - Add examples, prompt recipes, social launch content.
7. **Release readiness**
   - Changelog/versioning policy and upgrade notes scaffold.

## SEO/Keyword Guardrails

Use these naturally across headings and opening paragraphs:

- WordPress Ollama
- Local AI
- Self-hosted AI
- OpenAI compatible
- Local LLM
- Offline AI
- WordPress AI plugin
- Ollama provider
- Private AI
- AI provider

Rules:

- No keyword stuffing.
- Prefer concrete, technical phrasing over marketing hype.
- Explain tradeoffs (local privacy vs cloud convenience).

## Quality Bar

Every change should satisfy:

- Clear value to maintainers and first-time users.
- Copy-paste runnable examples.
- No fake badges, no fake stats, no inflated claims.
- Backward compatible unless explicitly versioned as breaking.

## Suggested Commit Grouping

Use small, logical conventional commits:

1. `docs: create AGENTS playbook for repo growth workflow`
2. `docs: rewrite README for onboarding and discoverability`
3. `chore: add community health files and contribution docs`
4. `ci: add markdown lint and docs validation workflow`
5. `docs: add demo assets guides and growth launch content`
6. `chore: add changelog and release management templates`

## Files to Add in Growth Pass

Expected additions during optimization:

- `CONTRIBUTING.md`
- `CODE_OF_CONDUCT.md`
- `DEVELOPMENT.md`
- `SECURITY.md`
- `CHANGELOG.md`
- `docs/TROUBLESHOOTING.md`
- `docs/ARCHITECTURE.md`
- `docs/EXAMPLES.md`
- `assets/` (screenshots, GIF placeholders, architecture diagram source)
- `.github/ISSUE_TEMPLATE/bug_report.yml`
- `.github/ISSUE_TEMPLATE/feature_request.yml`
- `.github/PULL_REQUEST_TEMPLATE.md`
- `.github/release_template.md` (or equivalent)
- `.github/workflows/markdown-lint.yml`

## Visual Asset Workflow

When real screenshots/GIFs are unavailable, commit:

- Asset checklist
- Naming convention
- Capture instructions
- Placeholder files + TODO markers

Keep this honest: never imply visuals are current if placeholders.

## Session Resume Checklist

At the start of each future session:

1. Check `git status`.
2. Read `README.md` and `AGENTS.md`.
3. Confirm what was completed in prior commit.
4. Pick the next highest impact task from Execution Order.
5. Make one logical change set and commit.

## Done Criteria for Repo Optimization

Consider this repo “growth-ready” when:

- README gives clear value in under 10 seconds.
- New user can install and test in under 10 minutes.
- Community files and templates exist.
- CI provides visible trust signals.
- Example usage and troubleshooting are comprehensive.
- Release process is explicit and repeatable.

## Notes for Future Agents

- Preserve technical credibility.
- Prefer incremental improvements over giant rewrites.
- If uncertain, optimize for reducing user setup friction first.
