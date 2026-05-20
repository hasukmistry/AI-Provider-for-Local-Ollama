# Contributing to AI Provider for Local Ollama

Thanks for contributing to this WordPress Ollama provider plugin.

This project focuses on reliable **Local AI / Self-hosted AI** integration for WordPress, so contributions should prioritize correctness, compatibility, and clear documentation.

## Before You Start

- Check existing issues before opening a new one.
- For bug reports, include exact reproduction steps.
- For feature requests, describe the real user workflow and expected behavior.
- Keep scope tight: one bug fix or one feature per pull request.

## Development Setup

1. Install and activate the [WordPress AI plugin](https://wordpress.org/plugins/ai/).
2. Install and run [Ollama](https://ollama.com/download).
3. Pull at least one local model:

```bash
ollama pull qwen2.5:7b
```

4. Confirm Ollama endpoint health:

```bash
curl http://localhost:11434
curl http://localhost:11434/api/tags
```

5. Place this plugin in your WordPress plugins directory and activate it.
6. Open `wp-admin/options-connectors.php` and configure endpoint `http://localhost:11434`.

## Pull Request Guidelines

- Write clear, minimal commits with conventional commit style when possible.
- Avoid unrelated refactors in the same pull request.
- Preserve existing behavior unless the change is explicitly intended as breaking.
- Update documentation when behavior, setup, or troubleshooting changes.

Include these in your PR description:

- What changed
- Why it changed
- How to test it
- Backward compatibility notes

## Testing Checklist

Before opening a PR, verify:

- Connector appears correctly in WordPress Connectors UI.
- Endpoint validation behaves correctly for valid and invalid endpoints.
- Model discovery works from `/api/tags`.
- Text generation provider/model selection still resolves as expected.
- No PHP warnings/notices introduced in normal setup flow.

## Code Style and Safety Expectations

- Keep code readable and incremental.
- Validate and sanitize settings-related inputs.
- Preserve capability checks around admin mutations.
- Do not introduce secrets, API keys, or local machine details into commits.

## Documentation Contributions

Documentation improvements are strongly encouraged, especially:

- Faster onboarding for first-time Ollama users
- Troubleshooting clarity
- Real screenshots and demo GIF updates
- Accurate compatibility notes

## Reporting Security Issues

Do not open public issues for sensitive vulnerabilities.

Instead, report responsibly to the maintainer with:

- Impact summary
- Reproduction details
- Suggested mitigation if available

## Release and Versioning Notes

- Use semantic versioning intent for user-facing behavior changes.
- Call out breaking changes explicitly in release notes.
- Keep upgrade notes actionable and concise.

## Questions

If requirements are unclear, open an issue first to align on scope before implementing major changes.
