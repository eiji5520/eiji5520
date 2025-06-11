import tkinter as tk
from tkinter import ttk, scrolledtext
import threading
import time
import os
from matplotlib.backends.backend_tkagg import FigureCanvasTkAgg
from matplotlib.figure import Figure


class DashboardGUI(tk.Tk):
    """Tkinter based dashboard GUI."""

    def __init__(self, log_path="logs/app.log"):
        super().__init__()
        self.title("Dashboard")
        self.log_path = log_path
        self._create_widgets()
        self._start_log_thread()

    def _create_widgets(self):
        self.notebook = ttk.Notebook(self)
        self.notebook.pack(fill=tk.BOTH, expand=True)

        self._create_dashboard_tab()
        self._create_schedules_tab()
        self._create_followers_tab()
        self._create_logs_tab()

    def _create_dashboard_tab(self):
        tab = ttk.Frame(self.notebook)
        self.notebook.add(tab, text="Dashboard")

        # table
        columns = ("Name", "Value", "Status")
        self.table = ttk.Treeview(tab, columns=columns, show="headings")
        for col in columns:
            self.table.heading(col, text=col)
        sample_data = [
            ("Metric A", "10", "OK"),
            ("Metric B", "5", "WARN"),
            ("Metric C", "2", "FAIL"),
        ]
        for row in sample_data:
            self.table.insert("", tk.END, values=row)
        self.table.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=5, pady=5)

        # graph
        figure = Figure(figsize=(4, 3))
        ax = figure.add_subplot(111)
        ax.plot([1, 2, 3, 4], [10, 20, 10, 30])
        ax.set_title("Sample Graph")
        canvas = FigureCanvasTkAgg(figure, master=tab)
        canvas.draw()
        canvas.get_tk_widget().pack(side=tk.RIGHT, fill=tk.BOTH, expand=True, padx=5, pady=5)

    def _create_schedules_tab(self):
        tab = ttk.Frame(self.notebook)
        self.notebook.add(tab, text="Schedules")

        self.schedules_list = tk.Listbox(tab)
        for i in range(5):
            self.schedules_list.insert(tk.END, f"Schedule {i}")
        self.schedules_list.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)

    def _create_followers_tab(self):
        tab = ttk.Frame(self.notebook)
        self.notebook.add(tab, text="Followers")

        self.followers_list = tk.Listbox(tab)
        for i in range(5):
            self.followers_list.insert(tk.END, f"Follower {i}")
        self.followers_list.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)

    def _create_logs_tab(self):
        tab = ttk.Frame(self.notebook)
        self.notebook.add(tab, text="Logs")

        self.log_text = scrolledtext.ScrolledText(tab, height=20)
        self.log_text.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)

    def _start_log_thread(self):
        thread = threading.Thread(target=self._tail_log, daemon=True)
        thread.start()

    def _tail_log(self):
        last_size = 0
        while True:
            if os.path.exists(self.log_path):
                size = os.path.getsize(self.log_path)
                if size > last_size:
                    with open(self.log_path, "r") as f:
                        f.seek(last_size)
                        new_lines = f.read()
                        self.log_text.insert(tk.END, new_lines)
                        self.log_text.see(tk.END)
                    last_size = size
            time.sleep(1)


if __name__ == "__main__":
    gui = DashboardGUI()
    gui.mainloop()
