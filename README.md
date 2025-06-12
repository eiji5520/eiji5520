# Insta Automation App

Desktop Instagram automation using Electron and Python. The backend exposes a FastAPI service which the Electron UI communicates with via secure IPC.

## Development

```bash
npm install
npm start
```

On first run, the app downloads an embedded Python distribution and installs the requirements listed in `python/requirements.txt`.
The FastAPI server runs on `http://localhost:3030`.
