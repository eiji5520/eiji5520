"""Agent module - handles communication with Ollama via OpenAI-compatible API."""

import json
import urllib.request
import urllib.error


class Agent:
    """Lightweight agent that talks to an OpenAI-compatible API (Ollama)."""

    def __init__(self, base_url: str, api_key: str, model: str):
        self.base_url = base_url.rstrip("/")
        self.api_key = api_key
        self.model = model
        self.history: list[dict] = []

    def _request(self, endpoint: str, payload: dict) -> dict:
        """Make a POST request to the API."""
        url = f"{self.base_url}{endpoint}"
        data = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(
            url,
            data=data,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {self.api_key}",
            },
            method="POST",
        )
        with urllib.request.urlopen(req, timeout=120) as resp:
            return json.loads(resp.read().decode("utf-8"))

    def chat(self, user_message: str, system_prompt: str | None = None) -> str:
        """Send a message and get a response."""
        if not self.history and system_prompt:
            self.history.append({"role": "system", "content": system_prompt})

        self.history.append({"role": "user", "content": user_message})

        payload = {
            "model": self.model,
            "messages": self.history,
            "stream": False,
        }

        result = self._request("/chat/completions", payload)
        reply = result["choices"][0]["message"]["content"]
        self.history.append({"role": "assistant", "content": reply})
        return reply

    def chat_stream(self, user_message: str, system_prompt: str | None = None):
        """Send a message and stream the response token by token."""
        if not self.history and system_prompt:
            self.history.append({"role": "system", "content": system_prompt})

        self.history.append({"role": "user", "content": user_message})

        payload = {
            "model": self.model,
            "messages": self.history,
            "stream": True,
        }

        url = f"{self.base_url}/chat/completions"
        data = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(
            url,
            data=data,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {self.api_key}",
            },
            method="POST",
        )

        full_reply = []
        with urllib.request.urlopen(req, timeout=120) as resp:
            for line in resp:
                line = line.decode("utf-8").strip()
                if not line.startswith("data: "):
                    continue
                data_str = line[6:]
                if data_str == "[DONE]":
                    break
                chunk = json.loads(data_str)
                delta = chunk["choices"][0].get("delta", {})
                content = delta.get("content", "")
                if content:
                    full_reply.append(content)
                    yield content

        self.history.append({"role": "assistant", "content": "".join(full_reply)})

    def reset(self):
        """Clear conversation history."""
        self.history.clear()

    def ping(self) -> bool:
        """Check if the API is reachable."""
        try:
            url = f"{self.base_url}/models"
            req = urllib.request.Request(
                url,
                headers={"Authorization": f"Bearer {self.api_key}"},
            )
            with urllib.request.urlopen(req, timeout=10) as resp:
                return resp.status == 200
        except Exception:
            return False

    def list_models(self) -> list[str]:
        """List available models from the API."""
        url = f"{self.base_url}/models"
        req = urllib.request.Request(
            url,
            headers={"Authorization": f"Bearer {self.api_key}"},
        )
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        return [m["id"] for m in data.get("data", [])]
