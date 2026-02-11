#!/usr/bin/env python3
"""OpenClaw setup script (Python version of setup.sh)
Usage: python install.py
"""

import os
import platform
import shutil
import subprocess
import sys
import time
import urllib.request
import urllib.error


def print_step(step: int, total: int, msg: str):
    print(f"\n[{step}/{total}] {msg}")


def check_command(name: str) -> bool:
    return shutil.which(name) is not None


def run(cmd: list[str], **kwargs) -> subprocess.CompletedProcess:
    return subprocess.run(cmd, **kwargs)


def main():
    print("=== OpenClaw Setup ===")
    print()

    # 1. Check Python
    print_step(1, 4, "Checking Python...")
    ver = sys.version_info
    if ver < (3, 10):
        print(f"  ERROR: Python 3.10+ required (found {ver.major}.{ver.minor})")
        sys.exit(1)
    print(f"  Python {ver.major}.{ver.minor} found.")

    # 2. Check / Install Ollama
    print_step(2, 4, "Checking Ollama...")
    if not check_command("ollama"):
        print("  Ollama not found. Installing...")
        system = platform.system()
        if system == "Darwin":
            print("  For macOS, please install Ollama from: https://ollama.com/download")
            print("  Or run: brew install ollama")
            print()
            print("  After installing, re-run this script.")
            sys.exit(1)
        elif system == "Windows":
            print("  For Windows, please install Ollama from: https://ollama.com/download")
            print()
            print("  After installing, re-run this script.")
            sys.exit(1)
        else:
            # Linux
            try:
                resp = urllib.request.urlopen("https://ollama.com/install.sh", timeout=30)
                install_script = resp.read()
                result = run(["sh"], input=install_script)
                if result.returncode != 0:
                    print("  ERROR: Ollama installation failed.")
                    sys.exit(1)
            except Exception as e:
                print(f"  ERROR: Could not install Ollama: {e}")
                sys.exit(1)
    else:
        result = run(["ollama", "--version"], capture_output=True, text=True)
        version_str = result.stdout.strip() if result.returncode == 0 else "installed"
        print(f"  Ollama found: {version_str}")

    # 3. Start Ollama and pull model
    print_step(3, 4, "Preparing Ollama model...")

    # Check if ollama serve is running
    ollama_running = False
    try:
        urllib.request.urlopen("http://localhost:11434/api/tags", timeout=5)
        ollama_running = True
    except Exception:
        pass

    if not ollama_running:
        print("  Starting ollama serve in background...")
        subprocess.Popen(
            ["ollama", "serve"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        time.sleep(3)

    # Check if model is already pulled
    result = run(["ollama", "list"], capture_output=True, text=True)
    if result.returncode == 0 and "llama3.2" in result.stdout:
        print("  Model llama3.2 is already available.")
    else:
        print("  Pulling llama3.2 (this may take a few minutes on first run)...")
        run(["ollama", "pull", "llama3.2"])

    # 4. Install OpenClaw
    print_step(4, 4, "Installing OpenClaw...")
    script_dir = os.path.dirname(os.path.abspath(__file__))
    result = run(
        [sys.executable, "-m", "pip", "install", "-e", script_dir],
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        print(f"  pip install failed: {result.stderr.splitlines()[-1] if result.stderr else 'unknown error'}")
        print("  Hint: Try running manually: pip install -e .")
        sys.exit(1)
    print("  Installed successfully.")

    # 5. Initialize config (import directly to avoid subprocess stdin issues)
    print()
    print("Initializing config...")
    sys.path.insert(0, script_dir)
    from openclaw.config import CONFIG_FILE, DEFAULT_CONFIG, save_config
    if CONFIG_FILE.exists():
        print(f"  Config already exists: {CONFIG_FILE} (keeping current)")
    else:
        save_config(DEFAULT_CONFIG)
        print(f"  Config created: {CONFIG_FILE}")

    print()
    print("=== Setup Complete ===")
    print()
    print("Quick start:")
    print("  openclaw doctor   # Check everything is working")
    print("  openclaw chat     # Start chatting with Llama 3.2")
    print("  openclaw models   # List available models")


if __name__ == "__main__":
    main()
