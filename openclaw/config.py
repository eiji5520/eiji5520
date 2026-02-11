"""Configuration management for OpenClaw."""

import json
import os
from pathlib import Path

CONFIG_DIR = Path.home() / ".openclaw"
CONFIG_FILE = CONFIG_DIR / "openclaw.json"

DEFAULT_CONFIG = {
    "agents": {
        "default": {
            "provider": "openai",
            "base_url": "http://localhost:11434/v1",
            "api_key": "ollama",
            "model": "llama3.2",
        }
    },
    "channels": {},
}


def ensure_config_dir():
    """Create config directory if it doesn't exist."""
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)


def load_config() -> dict:
    """Load configuration from file. Returns default config if file doesn't exist."""
    if not CONFIG_FILE.exists():
        return DEFAULT_CONFIG.copy()
    with open(CONFIG_FILE, "r", encoding="utf-8") as f:
        return json.load(f)


def save_config(config: dict):
    """Save configuration to file."""
    ensure_config_dir()
    with open(CONFIG_FILE, "w", encoding="utf-8") as f:
        json.dump(config, f, indent=2, ensure_ascii=False)
    return CONFIG_FILE


def init_config():
    """Initialize configuration with defaults if it doesn't exist."""
    if CONFIG_FILE.exists():
        return CONFIG_FILE
    save_config(DEFAULT_CONFIG)
    return CONFIG_FILE


def get_agent_config(agent_name: str = "default") -> dict:
    """Get configuration for a specific agent."""
    config = load_config()
    agents = config.get("agents", {})
    if agent_name not in agents:
        raise KeyError(f"Agent '{agent_name}' not found in config. Available: {list(agents.keys())}")
    return agents[agent_name]


def validate_config(config: dict) -> list[str]:
    """Validate configuration and return list of errors."""
    errors = []

    if "agents" not in config:
        errors.append("Missing 'agents' section")
        return errors

    agents = config["agents"]
    if not agents:
        errors.append("No agents configured")
        return errors

    for name, agent in agents.items():
        required_fields = ["provider", "base_url", "api_key", "model"]
        for field in required_fields:
            if field not in agent:
                errors.append(f"Agent '{name}': missing '{field}'")
            elif not agent[field]:
                errors.append(f"Agent '{name}': '{field}' is empty")

        if "base_url" in agent:
            url = agent["base_url"]
            if not url.startswith(("http://", "https://")):
                errors.append(f"Agent '{name}': 'base_url' must start with http:// or https://")

    return errors
