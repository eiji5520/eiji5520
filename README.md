# Dashboard GUI

This project provides a simple Tkinter based dashboard application. It shows
sample data across several tabs and displays log output from `logs/app.log`.

## Requirements

Install the dependencies using pip:

```bash
pip install -r requirements.txt
```

## Running

Execute the GUI with Python:

```bash
python src/dashboard_gui.py
```

The application will open with tabs for **Dashboard**, **Schedules**,
**Followers**, and **Logs**. The log tab follows updates to
`logs/app.log` in real time.
