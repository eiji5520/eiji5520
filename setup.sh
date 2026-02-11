#!/bin/bash
# OpenClaw setup script
# Usage: bash setup.sh

set -e

echo "=== OpenClaw Setup ==="
echo ""

# 1. Check Python
echo "[1/4] Checking Python..."
if ! command -v python3 &> /dev/null; then
    echo "ERROR: python3 not found. Please install Python 3.10+."
    exit 1
fi
PYVER=$(python3 -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')
echo "  Python $PYVER found."

# 2. Check/Install Ollama
echo ""
echo "[2/4] Checking Ollama..."
if ! command -v ollama &> /dev/null; then
    echo "  Ollama not found. Installing..."
    if [[ "$(uname)" == "Darwin" ]]; then
        echo "  For macOS, please install Ollama from: https://ollama.com/download"
        echo "  Or run: brew install ollama"
        echo ""
        echo "  After installing, re-run this script."
        exit 1
    else
        curl -fsSL https://ollama.com/install.sh | sh
    fi
else
    echo "  Ollama found: $(ollama --version 2>/dev/null || echo 'installed')"
fi

# 3. Start Ollama and pull model
echo ""
echo "[3/4] Preparing Ollama model..."

# Check if ollama serve is running
if ! curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    echo "  Starting ollama serve in background..."
    ollama serve > /dev/null 2>&1 &
    sleep 3
fi

# Pull llama3.2 if not present
if ollama list 2>/dev/null | grep -q "llama3.2"; then
    echo "  Model llama3.2 is already available."
else
    echo "  Pulling llama3.2 (this may take a few minutes on first run)..."
    ollama pull llama3.2
fi

# 4. Install OpenClaw
echo ""
echo "[4/4] Installing OpenClaw..."
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
pip install -e "$SCRIPT_DIR" --quiet 2>/dev/null || pip3 install -e "$SCRIPT_DIR" --quiet

# 5. Initialize config
echo ""
echo "Initializing config..."
openclaw init 2>/dev/null || python3 -m openclaw.cli init

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Quick start:"
echo "  openclaw doctor   # Check everything is working"
echo "  openclaw chat     # Start chatting with Llama 3.2"
echo "  openclaw models   # List available models"
