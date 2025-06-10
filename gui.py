import os
from datetime import datetime
from tkinter import filedialog, Tk, Label, Entry, Button, OptionMenu, StringVar

from celery_tasks import add_scheduled_post
from posting_time_recommender import recommend_posting_times


def choose_file(entry: Entry) -> None:
    """ファイル選択ダイアログを開き、選択したパスを入力欄に設定する。"""
    path = filedialog.askopenfilename()
    if path:
        entry.delete(0, 'end')
        entry.insert(0, path)


def schedule_post(path_entry: Entry, caption_entry: Entry, date_entry: Entry, type_var: StringVar, result_label: Label) -> None:
    """入力内容を読み込み、投稿をデータベースへ登録する。"""
    image_path = path_entry.get()
    caption = caption_entry.get()
    date_str = date_entry.get()
    options = {'フィード': 'feed', 'ストーリー': 'story', 'リール': 'reel'}
    media_type = options.get(type_var.get(), 'feed')

    if not os.path.exists(image_path):
        result_label.config(text='ファイルが見つかりません', fg='red')
        return

    try:
        post_time = datetime.fromisoformat(date_str)
        add_scheduled_post(image_path, caption, post_time, media_type)
        result_label.config(text='予約しました', fg='green')
    except Exception as e:
        result_label.config(text=str(e), fg='red')


def show_recommendations(csv_entry: Entry, result_label: Label) -> None:
    """CSV を読み込み、最適な投稿時間を表示する。"""
    csv_path = csv_entry.get()
    if not os.path.exists(csv_path):
        result_label.config(text='CSVファイルが見つかりません', fg='red')
        return
    try:
        hours = recommend_posting_times(csv_path)
        result_label.config(text='おすすめ時間: ' + ', '.join(map(str, hours)), fg='blue')
    except Exception as e:
        result_label.config(text=str(e), fg='red')


def main() -> None:
    """Tkinter ベースの簡易GUIを起動する。"""
    root = Tk()
    root.title('Instagram投稿スケジューラー')

    Label(root, text='メディアパス:').grid(row=0, column=0, sticky='e')
    path_entry = Entry(root, width=40)
    path_entry.grid(row=0, column=1)
    Button(root, text='参照', command=lambda: choose_file(path_entry)).grid(row=0, column=2)

    Label(root, text='キャプション:').grid(row=1, column=0, sticky='e')
    caption_entry = Entry(root, width=40)
    caption_entry.grid(row=1, column=1)
    
    Label(root, text='日時 (YYYY-MM-DD HH:MM):').grid(row=2, column=0, sticky='e')
    date_entry = Entry(root, width=20)
    date_entry.grid(row=2, column=1, sticky='w')
    
    Label(root, text='タイプ:').grid(row=3, column=0, sticky='e')
    options = {'フィード': 'feed', 'ストーリー': 'story', 'リール': 'reel'}
    type_var = StringVar(value='フィード')
    OptionMenu(root, type_var, *options.keys()).grid(row=3, column=1, sticky='w')

    result_label = Label(root, text='')
    result_label.grid(row=4, columnspan=3)

    Button(root, text='予約する', command=lambda: schedule_post(path_entry, caption_entry, date_entry, type_var, result_label)).grid(row=5, column=1)

    # Recommendation section
    Label(root, text='CSVパス:').grid(row=6, column=0, sticky='e')
    csv_entry = Entry(root, width=40)
    csv_entry.grid(row=6, column=1)
    Button(root, text='参照', command=lambda: choose_file(csv_entry)).grid(row=6, column=2)
    Button(root, text='おすすめ時間を表示', command=lambda: show_recommendations(csv_entry, result_label)).grid(row=7, column=1)

    root.mainloop()


if __name__ == '__main__':
    main()
