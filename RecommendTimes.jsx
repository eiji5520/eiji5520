import React from 'react';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
  ResponsiveContainer,
} from 'recharts';

export default function RecommendTimes({ times = [] }) {
  const sorted = [...times].sort((a, b) => b.score - a.score);

  return (
    <div className="space-y-4">
      <table className="min-w-full text-sm text-left">
        <thead>
          <tr className="border-b">
            <th className="p-2">Time</th>
            <th className="p-2">Score</th>
          </tr>
        </thead>
        <tbody>
          {sorted.map((t, i) => (
            <tr key={i} className="border-b">
              <td className="p-2">{t.time}</td>
              <td className="p-2">{t.score}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="w-full h-64">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={sorted}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="time" />
            <YAxis />
            <Tooltip />
            <Bar dataKey="score" fill="#3182ce" />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
