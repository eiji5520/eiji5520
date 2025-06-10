import React, { useEffect, useState } from 'react';
import {
  LineChart,
  Line,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

export default function Dashboard() {
  const [analytics, setAnalytics] = useState([]);
  const [actions, setActions] = useState([]);
  const [form, setForm] = useState({ date: '', type: 'feed', caption: '', file: null });

  useEffect(() => {
    fetch('/api/analytics')
      .then((res) => res.json())
      .then((data) => {
        setAnalytics(data.follower_growth || []);
        setActions(data.recent_actions || []);
      });
  }, []);

  const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });
  const handleFileChange = (e) => setForm({ ...form, file: e.target.files[0] });

  const handleSubmit = (e) => {
    e.preventDefault();
    const body = new FormData();
    body.append('date', form.date);
    body.append('type', form.type);
    body.append('caption', form.caption);
    if (form.file) body.append('file', form.file);
    fetch('/api/schedule', { method: 'POST', body })
      .then((res) => res.json())
      .then(() => {
        setForm({ date: '', type: 'feed', caption: '', file: null });
      });
  };

  return (
    <div className="container mx-auto p-4">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-white shadow rounded p-4">
        <h2 className="text-xl font-bold mb-2">フォロワーの増加</h2>
          <ResponsiveContainer width="100%" height={300}>
            <LineChart data={analytics}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="date" />
              <YAxis />
              <Tooltip />
              <Line type="monotone" dataKey="followers" stroke="#8884d8" />
            </LineChart>
          </ResponsiveContainer>
        </div>
        <div className="bg-white shadow rounded p-4">
          <h2 className="text-xl font-bold mb-2">最近のアクション</h2>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="text-left border-b">
                  <th className="p-2">日付</th>
                  <th className="p-2">アクション</th>
                  <th className="p-2">ステータス</th>
                </tr>
              </thead>
              <tbody>
                {actions.map((a, i) => (
                  <tr key={a.id || i} className="border-b">
                    <td className="p-2">{a.date}</td>
                    <td className="p-2 capitalize">{a.type}</td>
                    <td className="p-2">{a.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div className="bg-white shadow rounded p-4 mt-4">
        <h2 className="text-xl font-bold mb-2">新しい投稿を予約</h2>
        <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label className="block">
            <span className="text-sm">日時</span>
            <input
              type="datetime-local"
              name="date"
              value={form.date}
              onChange={handleChange}
              className="mt-1 p-2 w-full border rounded"
              required
            />
          </label>
          <label className="block">
            <span className="text-sm">タイプ</span>
            <select
              name="type"
              value={form.type}
              onChange={handleChange}
              className="mt-1 p-2 w-full border rounded"
            >
              <option value="feed">フィード</option>
              <option value="story">ストーリー</option>
              <option value="reel">リール</option>
            </select>
          </label>
          <label className="block md:col-span-2">
            <span className="text-sm">キャプション</span>
            <textarea
              name="caption"
              value={form.caption}
              onChange={handleChange}
              placeholder="キャプションを入力してください"
              className="mt-1 p-2 w-full border rounded"
            />
          </label>
          <label className="block md:col-span-2">
            <span className="text-sm">アップロード</span>
            <input type="file" onChange={handleFileChange} className="mt-1" />
          </label>
          <div className="md:col-span-2">
            <button type="submit" className="bg-blue-500 text-white px-4 py-2 rounded">
              予約する
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
