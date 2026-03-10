// ============================================================
// XBoard - 通知・DMページ
// リアルタイム通知一覧・DM簡易ビュー
// ============================================================

import React, { useState, useEffect } from 'react';
import { useAccountStore, useNotificationStore } from '../store';
import { NotificationData } from '../../shared/types';

const NOTIF_TYPE_LABELS: Record<string, string> = {
  like: 'いいね',
  repost: 'リポスト',
  reply: 'リプライ',
  follow: 'フォロー',
  mention: 'メンション',
  dm: 'DM',
};

export const NotificationsPage: React.FC = () => {
  const accounts = useAccountStore(s => s.accounts);
  const { notifications, loadNotifications } = useNotificationStore();
  const [selectedAccountId, setSelectedAccountId] = useState<string>('');
  const [activeTab, setActiveTab] = useState<'notifications' | 'dm'>('notifications');

  useEffect(() => {
    loadNotifications(selectedAccountId || undefined);
  }, [selectedAccountId]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 20px', borderBottom: '1px solid var(--border-color)' }}>
        <h1 style={{ fontSize: 20, fontWeight: 700 }}>通知・DM</h1>
        <div style={{ flex: 1 }} />
        <select className="select" style={{ maxWidth: 200 }} value={selectedAccountId} onChange={e => setSelectedAccountId(e.target.value)}>
          <option value="">すべてのアカウント</option>
          {accounts.map(a => <option key={a.id} value={a.id}>@{a.username}</option>)}
        </select>
        <button className="btn btn-ghost btn-sm" onClick={() => loadNotifications(selectedAccountId || undefined)}>更新</button>
      </div>

      <div className="tabs">
        <button className={`tab ${activeTab === 'notifications' ? 'active' : ''}`} onClick={() => setActiveTab('notifications')}>
          通知
        </button>
        <button className={`tab ${activeTab === 'dm' ? 'active' : ''}`} onClick={() => setActiveTab('dm')}>
          DM
        </button>
      </div>

      <div style={{ flex: 1, overflow: 'auto', padding: 20 }}>
        {activeTab === 'notifications' && (
          <>
            {notifications.length === 0 ? (
              <div className="empty-state">
                <h3>通知はありません</h3>
                <p>アカウントのセッションを開いて通知チェックを有効にしてください</p>
              </div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {notifications.map(notif => {
                  const account = accounts.find(a => a.id === notif.accountId);
                  return (
                    <div key={notif.id} className="card" style={{ padding: '12px 16px', opacity: notif.read ? 0.6 : 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                        <span className={`badge badge-info`} style={{ fontSize: 10 }}>{NOTIF_TYPE_LABELS[notif.type] || notif.type}</span>
                        {account && <span style={{ fontSize: 12, color: 'var(--text-accent)' }}>@{account.username}</span>}
                        <div style={{ flex: 1 }} />
                        <span style={{ fontSize: 11, color: 'var(--text-muted)' }}>{new Date(notif.timestamp).toLocaleString('ja-JP')}</span>
                      </div>
                      <div style={{ fontSize: 13 }}>
                        <span style={{ fontWeight: 600 }}>@{notif.fromUsername}</span>
                        {notif.content && <span style={{ marginLeft: 6 }}>{notif.content}</span>}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </>
        )}

        {activeTab === 'dm' && (
          <div className="empty-state">
            <h3>DM機能</h3>
            <p>アカウントのセッションを開いて、X公式サイトのDM画面を利用してください。<br />
            ここでは簡易チャットビューを表示します（セッション連携時に利用可能）。</p>
          </div>
        )}
      </div>
    </div>
  );
};
