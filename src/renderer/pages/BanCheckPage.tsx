// ============================================================
// XBoard - バンチェックページ
// サーチバン・リプライバン検出
// ============================================================

import React, { useState } from 'react';
import { useAccountStore } from '../store';
import { BanCheckResult } from '../../shared/types';

export const BanCheckPage: React.FC = () => {
  const accounts = useAccountStore(s => s.accounts);
  const [results, setResults] = useState<BanCheckResult[]>([]);
  const [checking, setChecking] = useState(false);
  const [checkingId, setCheckingId] = useState<string | null>(null);

  const activeAccounts = accounts.filter(a => a.status === 'active');

  const handleCheck = async (accountId: string) => {
    setChecking(true);
    setCheckingId(accountId);
    try {
      const result = await window.xboard.checkBan(accountId);
      setResults(prev => {
        const filtered = prev.filter(r => r.accountId !== accountId);
        return [result, ...filtered];
      });
    } catch (err: any) {
      console.error('バンチェックエラー:', err);
    } finally {
      setChecking(false);
      setCheckingId(null);
    }
  };

  const handleCheckAll = async () => {
    setChecking(true);
    for (const account of activeAccounts) {
      setCheckingId(account.id);
      try {
        const result = await window.xboard.checkBan(account.id);
        setResults(prev => {
          const filtered = prev.filter(r => r.accountId !== account.id);
          return [result, ...filtered];
        });
      } catch (err) {
        console.error(`@${account.username} チェックエラー:`, err);
      }
    }
    setChecking(false);
    setCheckingId(null);
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 20px', borderBottom: '1px solid var(--border-color)' }}>
        <h1 style={{ fontSize: 20, fontWeight: 700, flex: 1 }}>バンチェック</h1>
        <button className="btn btn-primary" onClick={handleCheckAll} disabled={checking || activeAccounts.length === 0}>
          {checking ? 'チェック中...' : '全アカウントチェック'}
        </button>
      </div>

      <div style={{ flex: 1, overflow: 'auto', padding: 20 }}>
        <div style={{ marginBottom: 20, padding: 12, background: 'var(--bg-tertiary)', borderRadius: 'var(--radius-md)', fontSize: 13, color: 'var(--text-secondary)' }}>
          <strong>バンチェックについて</strong><br />
          サーチバン: あなたのポストが検索結果に表示されなくなっている状態<br />
          リプライバン: あなたのリプライが他のユーザーに表示されにくくなっている状態<br />
          ※ チェックにはアカウントのセッションが開いている必要があります
        </div>

        {activeAccounts.length === 0 ? (
          <div className="empty-state">
            <h3>アクティブなアカウントがありません</h3>
            <p>ログイン済みのアカウントが必要です</p>
          </div>
        ) : (
          <table>
            <thead>
              <tr>
                <th>ユーザー名</th>
                <th>サーチバン</th>
                <th>リプライバン</th>
                <th>チェック日時</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              {activeAccounts.map(account => {
                const result = results.find(r => r.accountId === account.id);
                return (
                  <tr key={account.id}>
                    <td style={{ fontWeight: 600 }}>@{account.username}</td>
                    <td>
                      {result ? (
                        <span className={`badge ${result.searchBan ? 'badge-danger' : 'badge-success'}`}>
                          {result.searchBan ? 'バンあり' : '正常'}
                        </span>
                      ) : <span style={{ color: 'var(--text-muted)' }}>未チェック</span>}
                    </td>
                    <td>
                      {result ? (
                        <span className={`badge ${result.replyBan ? 'badge-danger' : 'badge-success'}`}>
                          {result.replyBan ? 'バンあり' : '正常'}
                        </span>
                      ) : <span style={{ color: 'var(--text-muted)' }}>未チェック</span>}
                    </td>
                    <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                      {result ? new Date(result.checkedAt).toLocaleString('ja-JP') : '-'}
                    </td>
                    <td>
                      <button className="btn btn-ghost btn-sm" onClick={() => handleCheck(account.id)} disabled={checking}>
                        {checkingId === account.id ? 'チェック中...' : 'チェック'}
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}

        {/* チェック結果サマリー */}
        {results.length > 0 && (
          <div className="card" style={{ marginTop: 20 }}>
            <h3 style={{ fontSize: 14, marginBottom: 8 }}>チェック結果サマリー</h3>
            <div style={{ display: 'flex', gap: 24, fontSize: 13 }}>
              <span>チェック済み: {results.length}件</span>
              <span style={{ color: 'var(--danger)' }}>サーチバン: {results.filter(r => r.searchBan).length}件</span>
              <span style={{ color: 'var(--danger)' }}>リプライバン: {results.filter(r => r.replyBan).length}件</span>
              <span style={{ color: 'var(--success)' }}>正常: {results.filter(r => !r.searchBan && !r.replyBan).length}件</span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
