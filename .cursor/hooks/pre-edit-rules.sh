#!/bin/bash
# Cursor — inject RULES.md chain before Edit/Write.
exec bash "$(cd "$(dirname "$0")/../.." && pwd)/cortex/hooks/pre-edit-rules-cursor.sh"
