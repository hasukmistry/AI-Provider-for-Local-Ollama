# AI Provider for Local Ollama

A custom WordPress connector/provider plugin that integrates a local Ollama instance with the WordPress Connectors API and AI plugin.

This plugin is designed for local/self-hosted Ollama usage and supports dynamic endpoint configuration from the Connectors setup UI.

## What this plugin does

- Registers a custom AI provider: `ollama_local`.
- Integrates with WordPress Connectors (`options-connectors.php`).
- Uses Ollama's OpenAI-compatible endpoint for text generation:
  - `POST /v1/chat/completions`
- Discovers available models dynamically:
  - `GET /api/tags`
- Performs endpoint health checks before considering the connector connected.
- Persists and auto-selects a default model from discovered model IDs.
- Enforces English output via system instruction guardrail.

## Plugin location

- Main plugin file: `ai-provider-for-local-ollama.php`
- Includes:
  - `includes/class-ollama-provider.php`
  - `includes/class-ollama-model-metadata-directory.php`
  - `includes/class-ollama-text-generation-model.php`
  - `includes/admin-connectors-ui.js`

## Requirements

- WordPress site with Connectors support.
- WordPress AI plugin installed/active:
  - Plugin URL: https://wordpress.org/plugins/ai/
  - Install and activate this plugin before activating AI Provider for Local Ollama.
- Local or reachable Ollama instance.
- Ollama model pulled locally (example: `qwen2.5:7b`).

## Install Ollama locally

### macOS

1. Install Ollama:
  - Download from `https://ollama.com/download`, or
  - Use Homebrew:
    - `brew install ollama`
2. Start Ollama service/app.
3. Confirm Ollama is running:
  - `curl http://localhost:11434`
  - Expected response contains: `Ollama is running`

### Linux (quick example)

1. Install Ollama:
  - `curl -fsSL https://ollama.com/install.sh | sh`
2. Start service:
  - `systemctl --user start ollama` (or run `ollama serve`)
3. Confirm health:
  - `curl http://localhost:11434`

### Windows

1. Install from `https://ollama.com/download`.
2. Launch Ollama.
3. Confirm endpoint:
  - `curl http://localhost:11434`

## Pull and run qwen2.5:7b locally

1. Pull model:
  - `ollama pull qwen2.5:7b`
2. Optional quick run test:
  - `ollama run qwen2.5:7b "Write one sentence about WordPress."`
3. Verify model exists in Ollama API:
  - `curl http://localhost:11434/api/tags`
  - Confirm `qwen2.5:7b` appears in the `models` list.

## Setup

1. Activate plugin **AI Provider for Local Ollama**.
2. Open Connectors page (`wp-admin/options-connectors.php`).
3. In Ollama connector Setup, enter endpoint URL, for example:
   - `http://localhost:11434`
4. Save.

## Endpoint validation behavior

On save, the plugin validates endpoint health.

A valid endpoint must pass at least one check:

- `GET {endpoint}` returns success and contains `Ollama is running`, or
- `GET {endpoint}/api/tags` returns success with JSON containing `models`.

If validation fails:

- The endpoint is cleared.
- Connector is treated as not connected.

## Connected state logic

Connector is marked connected only when:

- endpoint value exists, and
- endpoint passes health check.

This prevents false-positive connected states for invalid endpoints.

## Model selection logic

- Model IDs are fetched from `/api/tags`.
- First discovered model is persisted as default.
- Text generation preference injects:
  - provider: `ollama_local`
  - model: persisted default discovered model

If endpoint is unhealthy, model preference is not injected.

## Security hardening

The plugin includes hardening for endpoint and settings handling:

- Endpoint normalization only allows:
  - `http://` or `https://`
  - host + optional port
- Path/query/fragment/user-info are dropped.
- Settings mutation hooks are capability-gated (`manage_options` / multisite network equivalent).
- Settings hooks do not mutate on error responses.

## Caution

This plugin codebase was developed with significant AI assistance.

Because of that, there is no absolute guarantee of:

- output accuracy,
- complete security hardening for every environment,
- or zero edge-case bugs.

Please review, test, and security-audit before using in production.

## Troubleshooting

### Connector says connected but generation fails

- Verify endpoint: `http://localhost:11434`
- Verify Ollama is running.
- Verify model is available:
  - `curl http://localhost:11434/api/tags`

### Error: no model supports text_generation

- Ensure endpoint is healthy.
- Ensure `/api/tags` returns at least one model.
- Re-save endpoint in Setup to refresh default model selection.

### Wording still shows API Key

- Hard refresh browser (`Cmd+Shift+R`).
- Re-open connector setup.
- Ensure plugin is active and `includes/admin-connectors-ui.js` is loaded.

## Versioning

Current plugin header version: `0.2.0`

## Maintainer notes

This plugin evolved from a connector-visibility and compatibility troubleshooting workflow with WordPress Connectors and the AI plugin. If you change registration/auth flow, retest:

- Connector visibility in Connectors page
- Connected status persistence
- Title generation / text-generation support checks
- Endpoint validation behavior
