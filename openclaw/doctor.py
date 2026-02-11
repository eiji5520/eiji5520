"""Doctor command - diagnose OpenClaw configuration and connectivity."""

import json
import sys

from .config import CONFIG_FILE, load_config, validate_config
from .agent import Agent


def check_config_file() -> tuple[bool, str]:
    """Check if config file exists and is valid JSON."""
    if not CONFIG_FILE.exists():
        return False, f"Config file not found: {CONFIG_FILE}"
    try:
        with open(CONFIG_FILE, "r", encoding="utf-8") as f:
            json.load(f)
        return True, f"Config file OK: {CONFIG_FILE}"
    except json.JSONDecodeError as e:
        return False, f"Invalid JSON in config: {e}"


def check_config_schema() -> tuple[bool, list[str]]:
    """Validate config schema."""
    try:
        config = load_config()
    except Exception as e:
        return False, [str(e)]
    errors = validate_config(config)
    if errors:
        return False, errors
    return True, ["Config schema OK"]


def check_ollama_connection(agent_config: dict) -> tuple[bool, str]:
    """Check if Ollama API is reachable."""
    agent = Agent(
        base_url=agent_config["base_url"],
        api_key=agent_config["api_key"],
        model=agent_config["model"],
    )
    if agent.ping():
        return True, f"Connected to {agent_config['base_url']}"
    return False, f"Cannot connect to {agent_config['base_url']} - Is Ollama running?"


def check_model_available(agent_config: dict) -> tuple[bool, str]:
    """Check if the configured model is available."""
    agent = Agent(
        base_url=agent_config["base_url"],
        api_key=agent_config["api_key"],
        model=agent_config["model"],
    )
    try:
        models = agent.list_models()
        target = agent_config["model"]
        # Ollama model names may have ":latest" suffix
        found = any(target in m for m in models)
        if found:
            return True, f"Model '{target}' is available"
        return False, f"Model '{target}' not found. Available: {', '.join(models[:10])}"
    except Exception as e:
        return False, f"Cannot list models: {e}"


def run_doctor() -> int:
    """Run all diagnostic checks. Returns 0 if all pass, 1 otherwise."""
    print("OpenClaw Doctor")
    print("=" * 40)

    all_ok = True

    # 1. Config file
    print("\n[Config File]")
    ok, msg = check_config_file()
    print(f"  {'OK' if ok else 'NG'}: {msg}")
    if not ok:
        all_ok = False
        print("\n  Hint: Run 'openclaw init' to create a default config.")
        return 1

    # 2. Config schema
    print("\n[Config Schema]")
    ok, messages = check_config_schema()
    for msg in messages:
        print(f"  {'OK' if ok else 'NG'}: {msg}")
    if not ok:
        all_ok = False

    # 3. Load config for connectivity checks
    try:
        config = load_config()
    except Exception:
        return 1

    agents = config.get("agents", {})
    for name, agent_config in agents.items():
        # 4. Ollama connection
        print(f"\n[Agent: {name} - Connection]")
        ok, msg = check_ollama_connection(agent_config)
        print(f"  {'OK' if ok else 'NG'}: {msg}")
        if not ok:
            all_ok = False
            print("  Hint: Run 'ollama serve' to start the Ollama server.")
            continue

        # 5. Model availability
        print(f"\n[Agent: {name} - Model]")
        ok, msg = check_model_available(agent_config)
        print(f"  {'OK' if ok else 'NG'}: {msg}")
        if not ok:
            all_ok = False
            model = agent_config.get("model", "llama3.2")
            print(f"  Hint: Run 'ollama pull {model}' to download the model.")

    print("\n" + "=" * 40)
    if all_ok:
        print("All checks passed!")
        return 0
    else:
        print("Some checks failed. See hints above.")
        return 1
