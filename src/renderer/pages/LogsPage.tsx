// ============================================================
// XBoard - 操作ログページ
// 操作履歴の一覧・フィルタ・エクスポート
// ============================================================

import React, { useState, useEffect, useMemo } from 'react';
import { useLogStore, useAccountStore } from '../store';
import { OPERATION_TYPE_LABELS } from '../../shared/constants';

export const LogsPage: React.FC = () => {
  const { logs, loadLogs, exportLogs } = useLogStore();
  const accounts = useAccountStore(s => s.accounts);
  const [filterAccount, setFilterAccount] = useState('');
  const [filterType, setFilterType] = useState('');
  const [filterStatus, setFilterStatus] = useState('');

  useEffect(() => {
    loadLogs(500, filterAccount || undefined);
  }, [filterAccount]);

  const filteredLogs = useMemo(() => {
    let result = logs;
    if (filterType) result = result.filter(l => l.type === filterType);
    if (filterStatus) result = result.filter(l => l.status === filterStatus);
    return result;
  }, [logs, filterType, filterStatus]);

  const getAccountName = (id: string) => {
    const account = accounts.find(a => a.id === id);
    return account ? `@${account.username}` : id;
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 20px', borderBottom: '1px solid var(--border-color)' }}>
        <h1 style={{ fontSize: 20, fontWeight: 700, flex: 1 }}>操作ログ</h1>
        <button className="btn btn-ghost" onClick={exportLogs}>CSVエクスポート</button>
        <button className="btn btn-ghost btn-sm" onClick={() => loadLogs(500, filterAccount || undefined)}>更新</button>
      </div>

      {/* フィルタ */}
      <div style={{ display: 'flex', gap: 12, padding: '8px 20px', borderBottom: '1px solid var(--border-color)' }}>
        <select className="select" style={{ maxWidth: 180 }} value={filterAccount} onChange={e => setFilterAccount(e.target.value)}>
          <option value="">すべてのアカウント</option>
          {accounts.map(a => <option key={a.id} value={a.id}>@{a.username}</option>)}
        </select>
        <select className="select" style={{ maxWidth: 160 }} value={filterType} onChange={e => setFilterType(e.target.value)}>
          <option value="">すべての操作</option>
          {Object.entries(OPERATION_TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select className="select" style={{ maxWidth: 120 }} value={filterStatus} onChange={e => setFilterStatus(e.target.value)}>
          <option value="">すべての結果</option>
          <option value="success">成功</option>
          <option value="failure">失敗</option>
          <option value="pending">保留</option>
        </select>
        <span style={{ flex: 1 }} />
        <span style={{ fontSize: 12, color: 'var(--text-muted)', alignSelf: 'center' }}>{filteredLogs.length}件</span>
      </div>

      {/* ログ一覧 */}
      <div style={{ flex: 1, overflow: 'auto' }}>
        {filteredLogs.length === 0 ? (
          <div className="empty-state" style={{ paddingTop: 80 }}>
            <h3>ログがありません</h3>
            <p>操作を実行するとここにログが表示されます</p>
          </div>
        ) : (
          <table>
            <thead>
              <tr>
                <th>日時</th>
                <th>アカウント</th>
                <th>操作</th>
                <th>対象</th>
                <th>結果</th>
                <th>メッセージ</th>
              </tr>
            </thead>
            <tbody>
              {filteredLogs.map(log => (
                <tr key={log.id}>
                  <td style={{ fontSize: 12, color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>
                    {new Date(log.timestamp).toLocaleString('ja-JP')}
                  </td>
                  <td style={{ fontSize: 13, fontWeight: 500 }}>{getAccountName(log.accountId)}</td>
                  <td>
                    <span className="badge badge-info">{OPERATION_TYPE_LABELS[log.type] || log.type}</span>
                  </td>
                  <td style={{ fontSize: 12, maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {log.target || '-'}
                  </td>
                  <td>
                    <span className={`badge ${log.status === 'success' ? 'badge-success' : log.status === 'failure' ? 'badge-danger' : 'badge-warning'}`}>
                      {log.status === 'success' ? '成功' : log.status === 'failure' ? '失敗' : '保留'}
                    </span>
                  </td>
                  <td style={{ fontSize: 12, color: 'var(--text-secondary)', maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {log.message || '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
};
