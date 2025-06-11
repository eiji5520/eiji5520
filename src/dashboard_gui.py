"""Desktop admin interface for managing the Twitter automation tool."""
from __future__ import annotations

import csv
import threading
import time
import logging
from pathlib import Path
from typing import List

import PySimpleGUI as sg
import matplotlib.pyplot as plt
from matplotlib.backends.backend_tkagg import FigureCanvasTkAgg
import pandas as pd

from . import scheduler, followers

LOG = logging.getLogger(__name__)


class DashboardGUI:
    """Main dashboard window with multiple management tabs."""

    def __init__(
        self,
        schedule_file: str = "tweets.csv",
        analytics_file: str = "analytics_summary.csv",
        log_file: str = "automation.log",
    ) -> None:
        self.schedule_file = Path(schedule_file)
        self.analytics_file = Path(analytics_file)
        self.log_file = Path(log_file)
        self.window: sg.Window | None = None
        self._log_stop = threading.Event()
        self._log_thread: threading.Thread | None = None

    # ------------------------------------------------------------------
    # Layout helpers
    # ------------------------------------------------------------------
    def _dashboard_tab(self) -> List[List[sg.Element]]:
        graph_canvas = sg.Canvas(key="-GRAPH-")
        kpis = [
            [sg.Text("Total Impressions:"), sg.Text("0", key="-IMP-")],
            [sg.Text("Scheduled Tweets:"), sg.Text("0", key="-SCH_COUNT-")],
        ]
        return [[sg.Column(kpis), graph_canvas]]

    def _schedules_tab(self, data: list[list[str]]) -> List[List[sg.Element]]:
        header = ["datetime", "content"]
        table = sg.Table(
            values=data,
            headings=header,
            key="-TABLE-",
            enable_events=True,
            auto_size_columns=True,
            display_row_numbers=True,
        )
        buttons = [
            sg.Button("Add"),
            sg.Button("Edit"),
            sg.Button("Delete"),
        ]
        return [[table], buttons]

    def _followers_tab(self, users: list[str]) -> List[List[sg.Element]]:
        listbox = sg.Listbox(
            values=users,
            select_mode=sg.LISTBOX_SELECT_MODE_EXTENDED,
            size=(40, 15),
            key="-FOLLOWERS-",
        )
        return [[listbox], [sg.Button("Unfollow Selected")]]

    def _logs_tab(self) -> List[List[sg.Element]]:
        ml = sg.Multiline(size=(80, 20), key="-LOGS-", disabled=True, autoscroll=True)
        return [[ml]]

    def _menu(self) -> list[list[sg.Menu]]:
        menu_def = [["File", ["Login", "Refresh", "Exit"]]]
        return [[sg.Menu(menu_def)]]

    def _build_window(self, schedule_data: list[list[str]], followers_data: list[str]) -> sg.Window:
        tabs = [
            sg.Tab("Dashboard", self._dashboard_tab()),
            sg.Tab("Schedules", self._schedules_tab(schedule_data)),
            sg.Tab("Followers", self._followers_tab(followers_data)),
            sg.Tab("Logs", self._logs_tab()),
        ]
        layout = self._menu() + [[sg.TabGroup([tabs])]]
        return sg.Window("Twitter Automation Dashboard", layout, finalize=True)

    # ------------------------------------------------------------------
    # Data loaders
    # ------------------------------------------------------------------
    def _load_schedule(self) -> list[list[str]]:
        if not self.schedule_file.exists():
            return []
        df = pd.read_csv(self.schedule_file, parse_dates=["datetime"])
        return [[row["datetime"].strftime("%Y-%m-%d %H:%M"), row["content"]] for _, row in df.iterrows()]

    def _load_followers(self) -> list[str]:
        ffile = Path(followers.FollowerManager(None, "").data_file)
        if ffile.exists():
            try:
                df = pd.read_csv(ffile)
                return df["username"].tolist()
            except Exception:  # pragma: no cover - best effort
                pass
        return []

    def _load_impressions(self) -> int:
        if self.analytics_file.exists():
            try:
                df = pd.read_csv(self.analytics_file)
                return int(df["impressions"].sum())
            except Exception:  # pragma: no cover - best effort
                pass
        return 0

    # ------------------------------------------------------------------
    # Log thread
    # ------------------------------------------------------------------
    def _tail_log(self, window: sg.Window) -> None:
        position = 0
        while not self._log_stop.is_set():
            try:
                with open(self.log_file, "r") as fh:
                    fh.seek(position)
                    data = fh.read()
                    if data:
                        window.write_event_value("_LOG_UPDATE_", data)
                    position = fh.tell()
            except FileNotFoundError:
                window.write_event_value("_LOG_UPDATE_", "[log file not found]\n")
                position = 0
            time.sleep(1)

    # ------------------------------------------------------------------
    # GUI actions
    # ------------------------------------------------------------------
    def _start_log_thread(self, window: sg.Window) -> None:
        self._log_thread = threading.Thread(
            target=self._tail_log, args=(window,), daemon=True
        )
        self._log_thread.start()

    def _refresh(self) -> None:
        if not self.window:
            return
        schedule_data = self._load_schedule()
        self.window["-TABLE-"].update(values=schedule_data)
        self.window["-SCH_COUNT-"].update(str(len(schedule_data)))
        self.window["-IMP-"].update(str(self._load_impressions()))
        self._draw_graph()
        self.window["-FOLLOWERS-"].update(values=self._load_followers())

    def _draw_graph(self) -> None:
        if not self.window:
            return
        canvas_elem = self.window["-GRAPH-"]
        canvas = canvas_elem.TKCanvas
        for child in canvas.winfo_children():
            child.destroy()
        if self.analytics_file.exists():
            try:
                df = pd.read_csv(self.analytics_file, parse_dates=["date"])
                fig, ax = plt.subplots(figsize=(5, 2))
                df.plot(ax=ax)
                ax.set_ylabel("Impressions")
                fig_canvas = FigureCanvasTkAgg(fig, canvas)
                fig_canvas.draw()
                fig_canvas.get_tk_widget().pack(side="top", fill="both", expand=1)
            except Exception:  # pragma: no cover - best effort
                pass

    def _login(self) -> None:
        """Open a browser for the user to log in and save cookies."""
        driver = scheduler.webdriver.Chrome()
        try:
            sched = scheduler.TweetScheduler(driver)
            sched.load_session()
            sg.popup("Login cookies saved.")
        finally:
            driver.quit()

    def _add_schedule(self) -> None:
        layout = [
            [sg.Text("Datetime (YYYY-MM-DD HH:MM)"), sg.Input(key="-DT-")],
            [sg.Text("Content"), sg.Multiline(size=(40, 5), key="-CONTENT-")],
            [sg.Button("OK"), sg.Button("Cancel")],
        ]
        win = sg.Window("Add Tweet", layout)
        event, values = win.read()
        win.close()
        if event == "OK":
            with open(self.schedule_file, "a", newline="") as fh:
                writer = csv.writer(fh)
                if self.schedule_file.stat().st_size == 0:
                    writer.writerow(["datetime", "content"])
                writer.writerow([values["-DT-"], values["-CONTENT-"]])
            self._refresh()

    def _delete_schedule(self, index: int) -> None:
        df = pd.read_csv(self.schedule_file, parse_dates=["datetime"])
        df = df.drop(df.index[index])
        df.to_csv(self.schedule_file, index=False)
        self._refresh()

    def _edit_schedule(self, index: int, row: list[str]) -> None:
        layout = [
            [sg.Text("Datetime"), sg.Input(row[0], key="-DT-")],
            [sg.Text("Content"), sg.Multiline(row[1], size=(40, 5), key="-CONTENT-")],
            [sg.Button("OK"), sg.Button("Cancel")],
        ]
        win = sg.Window("Edit Tweet", layout)
        event, values = win.read()
        win.close()
        if event == "OK":
            df = pd.read_csv(self.schedule_file, parse_dates=["datetime"])
            df.loc[index, "datetime"] = values["-DT-"]
            df.loc[index, "content"] = values["-CONTENT-"]
            df.to_csv(self.schedule_file, index=False)
            self._refresh()

    def _unfollow_selected(self, usernames: list[str]) -> None:
        if not usernames:
            return
        driver = followers.webdriver.Chrome()
        try:
            mgr = followers.FollowerManager(driver, "")
            mgr.load_session()
            mgr.unfollow(usernames)
        finally:
            driver.quit()
        self._refresh()

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------
    def run(self) -> None:
        schedule_data = self._load_schedule()
        follower_data = self._load_followers()
        self.window = self._build_window(schedule_data, follower_data)
        self._start_log_thread(self.window)
        self._refresh()

        while True:
            event, values = self.window.read(timeout=100)
            if event in (sg.WIN_CLOSED, "Exit"):
                break
            if event == "Login":
                self._login()
            if event == "Refresh":
                self._refresh()
            if event == "Add":
                self._add_schedule()
            if event == "Delete" and values.get("-TABLE-"):
                self._delete_schedule(values["-TABLE-"][0])
            if event == "Edit" and values.get("-TABLE-"):
                idx = values["-TABLE-"][0]
                row = self.window["-TABLE-"].Values[idx]
                self._edit_schedule(idx, row)
            if event == "Unfollow Selected":
                self._unfollow_selected(values["-FOLLOWERS-"])
            if event == "_LOG_UPDATE_":
                self.window["-LOGS-"].print(values[event], end="")
        self._log_stop.set()
        if self._log_thread:
            self._log_thread.join(timeout=1)
        self.window.close()


def main() -> None:
    """Entry point for running the dashboard."""
    gui = DashboardGUI()
    gui.run()


if __name__ == "__main__":
    main()
