#!/usr/bin/env bash
# Install the image embedder: service venv (.venv) + the exported ONNX model (models/siglip-base-p16-224.onnx).
# Idempotent: an existing venv is updated from requirements.txt, an existing model is kept.
#
# usage: scripts/setup.sh [--onnx /path/to/existing.onnx] [--force-export]
#   --onnx PATH     copy an already exported model instead of exporting (e.g. from another machine)
#   --force-export  re-export even if the model file exists
#
# The export downloads google/siglip-base-patch16-224 (~813 MB) into a throw-away venv + HF cache under a temp dir,
# writes the 372 MB vision-tower ONNX and deletes the temp dir. The service venv never contains torch.
set -euo pipefail
export PIP_NO_CACHE_DIR=1 PIP_DISABLE_PIP_VERSION_CHECK=1
umask 022   # root installs; the service user (www-data) only needs to read the venv and the model

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VENV="${EMBEDDER_VENV:-$ROOT/.venv}"
MODEL="${EMBEDDER_MODEL_PATH:-$ROOT/models/siglip-base-p16-224.onnx}"
PYTHON="${PYTHON:-python3}"
SRC_ONNX=""; FORCE=0
while [ $# -gt 0 ]; do
  case "$1" in
    --onnx) SRC_ONNX="$2"; shift 2 ;;
    --force-export) FORCE=1; shift ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

say() { printf '\033[1;36m==> %s\033[0m\n' "$*"; }

"$PYTHON" -c 'import sys; sys.exit(0 if sys.version_info >= (3, 11) else 1)' || { echo "Python 3.11+ required"; exit 1; }

if [ ! -x "$VENV/bin/python" ]; then
  say "Creating venv $VENV"
  "$PYTHON" -m venv "$VENV"
fi
say "Installing service requirements"
"$VENV/bin/pip" install --quiet --upgrade pip
"$VENV/bin/pip" install --quiet -r "$ROOT/requirements.txt"

mkdir -p "$(dirname "$MODEL")"
if [ -n "$SRC_ONNX" ]; then
  say "Copying model from $SRC_ONNX"
  install -m 0644 "$SRC_ONNX" "$MODEL.tmp" && mv "$MODEL.tmp" "$MODEL"
elif [ ! -s "$MODEL" ] || [ "$FORCE" -eq 1 ]; then
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  say "Exporting SigLIP vision tower to ONNX (temporary venv in $TMP)"
  "$PYTHON" -m venv "$TMP/venv"
  "$TMP/venv/bin/pip" install --quiet --upgrade pip
  "$TMP/venv/bin/pip" install --quiet -r "$ROOT/requirements-export.txt"
  HF_HOME="$TMP/hf" "$TMP/venv/bin/python" -I "$ROOT/scripts/export_onnx.py" "$TMP/model.onnx"
  install -m 0644 "$TMP/model.onnx" "$MODEL.tmp" && mv "$MODEL.tmp" "$MODEL"
else
  say "Model already present: $MODEL"
fi

say "Checking the model loads"
(cd "$ROOT" && EMBEDDER_MODEL_PATH="$MODEL" "$VENV/bin/python" -c '
import os
from app.model import Embedder
e = Embedder(os.environ["EMBEDDER_MODEL_PATH"], "check", 2)
assert e.dim == 768, e.dim
print("model ok, dim", e.dim)')
say "Done"
