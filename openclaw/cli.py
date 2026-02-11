"""CLI entry point for OpenClaw."""

import sys

from .config import (
    CONFIG_FILE,
    init_config,
    load_config,
    get_agent_config,
    save_config,
)
from .agent import Agent
from .doctor import run_doctor


def cmd_init():
    """Initialize OpenClaw configuration."""
    if CONFIG_FILE.exists():
        print(f"Config already exists: {CONFIG_FILE}")
        answer = input("Overwrite with defaults? [y/N]: ").strip().lower()
        if answer != "y":
            print("Aborted.")
            return
        from .config import DEFAULT_CONFIG
        save_config(DEFAULT_CONFIG)
        print(f"Config reset to defaults: {CONFIG_FILE}")
    else:
        path = init_config()
        print(f"Config created: {path}")


def cmd_config():
    """Show current configuration."""
    import json
    config = load_config()
    print(json.dumps(config, indent=2, ensure_ascii=False))


def cmd_doctor():
    """Run diagnostic checks."""
    sys.exit(run_doctor())


def cmd_chat(agent_name: str = "default"):
    """Interactive chat with an agent."""
    try:
        agent_config = get_agent_config(agent_name)
    except KeyError as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)

    agent = Agent(
        base_url=agent_config["base_url"],
        api_key=agent_config["api_key"],
        model=agent_config["model"],
    )

    model = agent_config["model"]
    print(f"OpenClaw Chat (model: {model})")
    print(f"Type 'quit' or 'exit' to end. 'reset' to clear history.")
    print("-" * 40)

    system_prompt = "You are a helpful assistant. Answer concisely in the user's language."

    while True:
        try:
            user_input = input("\nYou: ").strip()
        except (EOFError, KeyboardInterrupt):
            print("\nBye!")
            break

        if not user_input:
            continue
        if user_input.lower() in ("quit", "exit"):
            print("Bye!")
            break
        if user_input.lower() == "reset":
            agent.reset()
            print("Conversation history cleared.")
            continue

        print("\nAssistant: ", end="", flush=True)
        try:
            for token in agent.chat_stream(user_input, system_prompt=system_prompt):
                print(token, end="", flush=True)
            print()
        except Exception as e:
            print(f"\nError: {e}", file=sys.stderr)
            print("Hint: Is Ollama running? Try 'ollama serve'", file=sys.stderr)


def cmd_models(agent_name: str = "default"):
    """List available models."""
    try:
        agent_config = get_agent_config(agent_name)
    except KeyError as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)

    agent = Agent(
        base_url=agent_config["base_url"],
        api_key=agent_config["api_key"],
        model=agent_config["model"],
    )

    try:
        models = agent.list_models()
        current = agent_config["model"]
        print("Available models:")
        for m in models:
            marker = " <-- current" if current in m else ""
            print(f"  - {m}{marker}")
    except Exception as e:
        print(f"Error listing models: {e}", file=sys.stderr)
        print("Hint: Is Ollama running? Try 'ollama serve'", file=sys.stderr)
        sys.exit(1)


USAGE = """\
Usage: openclaw <command> [options]

Commands:
  init      Create default config (~/.openclaw/openclaw.json)
  config    Show current configuration
  doctor    Check config, connectivity, and model availability
  chat      Interactive chat with the local LLM
  models    List available models from Ollama

Options:
  --agent NAME    Specify agent name (default: "default")

Examples:
  openclaw init
  openclaw doctor
  openclaw chat
  openclaw models
"""


def main():
    args = sys.argv[1:]

    if not args or args[0] in ("-h", "--help", "help"):
        print(USAGE)
        sys.exit(0)

    command = args[0]

    # Parse --agent flag
    agent_name = "default"
    if "--agent" in args:
        idx = args.index("--agent")
        if idx + 1 < len(args):
            agent_name = args[idx + 1]

    if command == "init":
        cmd_init()
    elif command == "config":
        cmd_config()
    elif command == "doctor":
        cmd_doctor()
    elif command == "chat":
        cmd_chat(agent_name)
    elif command == "models":
        cmd_models(agent_name)
    else:
        print(f"Unknown command: {command}")
        print(USAGE)
        sys.exit(1)


if __name__ == "__main__":
    main()
