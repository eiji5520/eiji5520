import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression


def recommend_posting_times(data_path: str) -> list[int]:
    """Return top 3 posting hours predicted to maximize engagement.

    Parameters
    ----------
    data_path : str
        Path to a CSV file containing columns 'timestamp', 'likes', and
        'comments'.

    Returns
    -------
    list[int]
        Three hours of the day (0-23) sorted by predicted engagement in
        descending order.
    """
    df = pd.read_csv(data_path, parse_dates=["timestamp"])
    df["hour"] = df["timestamp"].dt.hour
    df["engagement"] = df["likes"] + df["comments"]

    X = df[["hour"]]
    y = df["engagement"]

    model = LinearRegression()
    model.fit(X, y)

    hours = np.arange(24).reshape(-1, 1)
    predictions = model.predict(hours)
    best_indices = np.argsort(predictions)[::-1][:3]
    best_hours = hours.flatten()[best_indices]
    return best_hours.tolist()
