// ============================================================
// XBoard - 一括操作ページ
// 複数アカウントへのポスト・いいね・リポスト・ブックマーク・フォロー
// ============================================================

import React, { useState, useEffect } from 'react';
import { useAccountStore, useBulkStore } from '../store';
import { ACCOUNT_STATUS_LABELS } from '../../shared/constants';

type OperationType = 'post' | 'like' | 'repost' | 'bookmark' | 'follow';

const OP_LABELS: Record<OperationType, string> = {
  post: 'ポスト投稿',
  like: 'いいね',
  repost: 'リポスト',
  bookmark: 'ブックマーク',
  follow: 'フォロー',
};

export const BulkOperationsPage: React.FC = () => {
  const { accounts, selectedAccountIds, selectAccount, selectAll, clearSelection } = useAccountStore();
  const { isExecuting, progress, executeBulk, resetProgress } = useBulkStore();

  const [opType, setOpType] = useState<OperationType>('post');
  const [postContent, setPostContent] = useState('');
  const [targetUrl, setTargetUrl] = useState('');
  const [targetUsername, setTargetUsername] = useState('');
  const [delayMs, setDelayMs] = useState(2000);

  // 一括操作の進捗リスナー
  useEffect(() => {
    const cleanup = window.xboard.onBulkProgress((prog) => {
      useBulkStore.setState({ progress: prog });
    });
    return cleanup;
  }, []);

  const activeAccounts = accounts.filter(a => a.status === 'active');
  const selectedActive = selectedAccountIds.filter(id => activeAccounts.some(a => a.id === id));

  const handleExecute = async () => {
    if (selectedActive.length === 0) return;

    await executeBulk({
      type: opType,
      accountIds: selectedActive,
      content: opType === 'post' ? postContent : undefined,
      targetUrl: ['like', 'repost', 'bookmark'].includes(opType) ? targetUrl : undefined,
      targetUsername: opType === 'follow' ? targetUsername.replace(/^@/, '') : undefined,
      delayMs,
    });
  };

  const progressPct = progress ? Math.round((progress.completed / progress.total) * 100) : 0;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      {/* ヘッダー */}
      <div style={{ padding: '12px 20px', borderBottom: '1px solid var(--border-color)' }}>
        <h1 style={{ fontSize: 20, fontWeight: 700 }}>一括操作</h1>
      </div>

      <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>
        {/* 左: アカウント選択 */}
        <div style={{ width: 320, borderRight: '1px solid var(--border-color)', display: 'flex', flexDirection: 'column' }}>
          <div style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-color)', display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 13, fontWeight: 600 }}>アカウント選択</span>
            <span style={{ fontSize: 11, color: 'var(--text-muted)' }}>{selectedActive.length}件</span>
            <div style={{ flex: 1 }} />
            <button className="btn btn-ghost btn-sm" onClick={selectAll}>全選択</button>
            <button className="btn btn-ghost btn-sm" onClick={clearSelection}>解除</button>
          </div>
          <div style={{ flex: 1, overflow: 'auto', padding: 8 }}>
            {activeAccounts.length === 0 ? (
              <div className="empty-state"><p>アクティブなアカウントがありません</p></div>
            ) : (
              activeAccounts.map(account => (
                <label key={account.id} className="checkbox-wrapper" style={{ padding: '6px 8px', borderRadius: 4, background: selectedAccountIds.includes(account.id) ? 'var(--bg-hover)' : 'transparent' }}>
                  <input type="checkbox" checked={selectedAccountIds.includes(account.id)} onChange={() => selectAccount(account.id, true)} />
                  <span style={{ fontSize: 13 }}>@{account.username}</span>
                </label>
              ))
            )}
          </div>
        </div>

        {/* 右: 操作パネル */}
        <div style={{ flex: 1, padding: 20, overflow: 'auto' }}>
          {/* 操作タイプ選択 */}
          <div className="tabs" style={{ marginBottom: 20 }}>
            {(Object.keys(OP_LABELS) as OperationType[]).map(t => (
              <button key={t} className={`tab ${opType === t ? 'active' : ''}`} onClick={() => setOpType(t)}>
                {OP_LABELS[t]}
              </button>
            ))}
          </div>

          {/* 操作内容入力 */}
          <div className="card" style={{ marginBottom: 20 }}>
            {opType === 'post' && (
              <div className="form-group">
                <label>投稿内容</label>
                <textarea className="textarea" value={postContent} onChange={e => setPostContent(e.target.value)} placeholder="投稿するテキストを入力..." rows={4} />
                <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4, textAlign: 'right' }}>{postContent.length} / 280</div>
              </div>
            )}

            {['like', 'repost', 'bookmark'].includes(opType) && (
              <div className="form-group">
                <label>対象ポストURL</label>
                <input className="input" value={targetUrl} onChange={e => setTargetUrl(e.target.value)} placeholder="https://x.com/user/status/1234567890" />
              </div>
            )}

            {opType === 'follow' && (
              <div className="form-group">
                <label>フォロー対象ユーザー名</label>
                <input className="input" value={targetUsername} onChange={e => setTargetUsername(e.target.value)} placeholder="@username" />
              </div>
            )}

            <div className="form-group">
              <label>操作間遅延（ミリ秒）</label>
              <input className="input" type="number" value={delayMs} onChange={e => setDelayMs(parseInt(e.target.value) || 2000)} min={500} max={30000} style={{ maxWidth: 160 }} />
              <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4 }}>アカウント間に一定の遅延を入れることで検出リスクを軽減</div>
            </div>
          </div>

          {/* 実行ボタン */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20 }}>
            <button className="btn btn-primary" onClick={handleExecute} disabled={isExecuting || selectedActive.length === 0}>
              {isExecuting ? '実行中...' : `${selectedActive.length}アカウントで実行`}
            </button>
            {progress && (
              <button className="btn btn-ghost" onClick={resetProgress}>結果をクリア</button>
            )}
          </div>

          {/* 進捗表示 */}
          {progress && (
            <div className="card">
              <div style={{ marginBottom: 8 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                  <span>進捗: {progress.completed} / {progress.total}</span>
                  <span>{progressPct}%</span>
                </div>
                <div className="progress-bar" style={{ marginTop: 4 }}>
                  <div className="progress-bar-fill" style={{ width: `${progressPct}%` }} />
                </div>
              </div>
              <div style={{ display: 'flex', gap: 16, fontSize: 13 }}>
                <span style={{ color: 'var(--success)' }}>成功: {progress.succeeded}</span>
                <span style={{ color: 'var(--danger)' }}>失敗: {progress.failed}</span>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
