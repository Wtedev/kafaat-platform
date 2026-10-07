#!/usr/bin/env bash
# Staging is paused. Do not deploy to it, and do not link the CLI to that environment.
# Production changes go through a pull request to main.
echo "Staging is paused. Refusing to deploy. Open a PR to main instead." >&2
exit 1
