// ============================================================
// XBoard - アカウント管理ページ
// アカウント一覧、追加・編集・削除、グループ管理
// ============================================================

import React, { useState, useMemo } from 'react';
import { useAccountStore, useGroupStore, useUIStore } from '../store';
import { Account, ProxyConfig } from '../../shared/types';
import { ACCOUNT_STATUS_LABELS, GROUP_COLORS } from '../../shared/constants';

// ============================================================
// アカウント追加/編集モーダル
// ============================================================

interface AccountFormProps {
  account?: Account;
  onSave: (data: Partial<Account>) => void;
  onClose: () => void;
}

const AccountForm: React.FC<AccountFormProps> = ({ account, onSave, onClose }) => {
  const [username, setUsername] = useState(account?.username || '');
  const [displayName, setDisplayName] = useState(account?.displayName || '');
  const [notes, setNotes] = useState(account?.notes || '');
  const [authToken, setAuthToken] = useState('');
  const [proxyEnabled, setProxyEnabled] = useState(account?.proxy?.enabled || false);
  const [proxyHost, setProxyHost] = useState(account?.proxy?.host || '');
  const [proxyPort, setProxyPort] = useState(account?.proxy?.port?.toString() || '');
  const [proxyProtocol, setProxyProtocol] = useState(account?.proxy?.protocol || 'http');
  const [proxyUser, setProxyUser] = useState(account?.proxy?.username || '');
  const [proxyPass, setProxyPass] = useState('');
  const groups = useGroupStore(s => s.groups);
  const [selectedGroups, setSelectedGroups] = useState<string[]>(account?.groupIds || []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const proxy: ProxyConfig | undefined = proxyEnabled
      ? { enabled: true, host: proxyHost, port: parseInt(proxyPort) || 8080, protocol: proxyProtocol as any, username: proxyUser || undefined, password: proxyPass || undefined }
      : undefined;

    onSave({
      ...(account ? { id: account.id } : {}),
      username: username.replace(/^@/, ''),
      displayName: displayName || username,
      groupIds: selectedGroups,
      proxy,
      authToken: authToken || undefined,
      notes: notes || undefined,
    });
    onClose();
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()} style={{ minWidth: 520 }}>
        <div className="modal-header">
          <h2>{account ? 'アカウント編集' : 'アカウント追加'}</h2>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>ユーザー名 *</label>
            <input className="input" value={username} onChange={e => setUsername(e.target.value)} placeholder="@username" required />
          </div>
          <div className="form-group">
            <label>表示名</label>
            <input className="input" value={displayName} onChange={e => setDisplayName(e.target.value)} placeholder="表示名" />
          </div>
          <div className="form-group">
            <label>認証トークン（任意）</label>
            <input className="input" type="password" value={authToken} onChange={e => setAuthToken(e.target.value)} placeholder="暗号化して保存されます" />
          </div>

          {groups.length > 0 && (
            <div className="form-group">
              <label>グループ</label>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                {groups.map(g => (
                  <label key={g.id} className="checkbox-wrapper" style={{ padding: '4px 8px', background: selectedGroups.includes(g.id) ? `${g.color}20` : 'transparent', border: `1px solid ${g.color}40`, borderRadius: 4 }}>
                    <input type="checkbox" checked={selectedGroups.includes(g.id)} onChange={() => setSelectedGroups(prev => prev.includes(g.id) ? prev.filter(i => i !== g.id) : [...prev, g.id])} />
                    <span style={{ color: g.color, fontSize: 12 }}>{g.name}</span>
                  </label>
                ))}
              </div>
            </div>
          )}

          {/* プロキシ設定 */}
          <div className="form-group">
            <label className="checkbox-wrapper">
              <input type="checkbox" checked={proxyEnabled} onChange={e => setProxyEnabled(e.target.checked)} />
              <span>プロキシを使用</span>
            </label>
          </div>
          {proxyEnabled && (
            <>
              <div className="form-row">
                <div className="form-group">
                  <label>プロトコル</label>
                  <select className="select" value={proxyProtocol} onChange={e => setProxyProtocol(e.target.value)}>
                    <option value="http">HTTP</option>
                    <option value="https">HTTPS</option>
                    <option value="socks4">SOCKS4</option>
                    <option value="socks5">SOCKS5</option>
                  </select>
                </div>
                <div className="form-group">
                  <label>ホスト</label>
                  <input className="input" value={proxyHost} onChange={e => setProxyHost(e.target.value)} placeholder="proxy.example.com" />
                </div>
                <div className="form-group" style={{ maxWidth: 100 }}>
                  <label>ポート</label>
                  <input className="input" value={proxyPort} onChange={e => setProxyPort(e.target.value)} placeholder="8080" />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>プロキシユーザー名</label>
                  <input className="input" value={proxyUser} onChange={e => setProxyUser(e.target.value)} />
                </div>
                <div className="form-group">
                  <label>プロキシパスワード</label>
                  <input className="input" type="password" value={proxyPass} onChange={e => setProxyPass(e.target.value)} />
                </div>
              </div>
            </>
          )}

          <div className="form-group">
            <label>メモ</label>
            <textarea className="textarea" value={notes} onChange={e => setNotes(e.target.value)} rows={2} />
          </div>

          <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 16 }}>
            <button type="button" className="btn btn-ghost" onClick={onClose}>キャンセル</button>
            <button type="submit" className="btn btn-primary">{account ? '更新' : '追加'}</button>
          </div>
        </form>
      </div>
    </div>
  );
};

// ============================================================
// グループ管理モーダル
// ============================================================

const GroupManager: React.FC<{ onClose: () => void }> = ({ onClose }) => {
  const { groups, addGroup, updateGroup, deleteGroup } = useGroupStore();
  const [newName, setNewName] = useState('');
  const [newColor, setNewColor] = useState(GROUP_COLORS[0]);

  const handleAdd = async () => {
    if (!newName.trim()) return;
    await addGroup({ name: newName.trim(), color: newColor });
    setNewName('');
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>グループ管理</h2>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <input className="input" value={newName} onChange={e => setNewName(e.target.value)} placeholder="グループ名" style={{ flex: 1 }} />
          <select className="select" value={newColor} onChange={e => setNewColor(e.target.value)} style={{ width: 60 }}>
            {GROUP_COLORS.map(c => <option key={c} value={c} style={{ color: c }}>{c}</option>)}
          </select>
          <button className="btn btn-primary" onClick={handleAdd}>追加</button>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {groups.map(g => (
            <div key={g.id} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: 8, background: 'var(--bg-hover)', borderRadius: 4 }}>
              <span style={{ width: 12, height: 12, background: g.color, borderRadius: '50%', flexShrink: 0 }} />
              <span style={{ flex: 1 }}>{g.name}</span>
              <button className="btn btn-danger btn-sm" onClick={() => deleteGroup(g.id)}>削除</button>
            </div>
          ))}
          {groups.length === 0 && <div className="empty-state"><p>グループがありません</p></div>}
        </div>
      </div>
    </div>
  );
};

// ============================================================
// メインページ
// ============================================================

export const AccountsPage: React.FC = () => {
  const { accounts, selectedAccountIds, selectAccount, selectAll, clearSelection, deleteAccount, filterGroupId, setFilterGroup, searchQuery, setSearchQuery, importCSV, exportCSV } = useAccountStore();
  const { addAccount, updateAccount } = useAccountStore();
  const groups = useGroupStore(s => s.groups);
  const { openModal, closeModal, modalOpen } = useUIStore();

  const [editingAccount, setEditingAccount] = useState<Account | undefined>();

  // フィルタリング
  const filteredAccounts = useMemo(() => {
    let result = accounts;
    if (filterGroupId) {
      result = result.filter(a => a.groupIds.includes(filterGroupId));
    }
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      result = result.filter(a =>
        a.username.toLowerCase().includes(q) ||
        a.displayName.toLowerCase().includes(q) ||
        a.notes?.toLowerCase().includes(q)
      );
    }
    return result;
  }, [accounts, filterGroupId, searchQuery]);

  const handleSave = async (data: Partial<Account>) => {
    if (data.id) {
      await updateAccount(data as any);
    } else {
      await addAccount(data);
    }
  };

  const handleDelete = async () => {
    if (selectedAccountIds.length === 0) return;
    for (const id of selectedAccountIds) {
      await deleteAccount(id);
    }
  };

  const handleOpenSession = async (accountId: string) => {
    await window.xboard.loginAccount(accountId);
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      {/* ヘッダー */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 20px', borderBottom: '1px solid var(--border-color)' }}>
        <h1 style={{ fontSize: 20, fontWeight: 700, flex: 1 }}>アカウント管理</h1>
        <input className="input" style={{ maxWidth: 220 }} placeholder="検索..." value={searchQuery} onChange={e => setSearchQuery(e.target.value)} />
        <select className="select" style={{ maxWidth: 160 }} value={filterGroupId || ''} onChange={e => setFilterGroup(e.target.value || null)}>
          <option value="">すべてのグループ</option>
          {groups.map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
        </select>
      </div>

      {/* ツールバー */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 20px', borderBottom: '1px solid var(--border-color)', flexWrap: 'wrap' }}>
        <button className="btn btn-primary" onClick={() => { setEditingAccount(undefined); openModal('account-form'); }}>+ 追加</button>
        <button className="btn btn-ghost" onClick={() => openModal('group-manager')}>グループ管理</button>
        <button className="btn btn-ghost" onClick={importCSV}>CSVインポート</button>
        <button className="btn btn-ghost" onClick={exportCSV}>CSVエクスポート</button>
        <div style={{ flex: 1 }} />
        {selectedAccountIds.length > 0 && (
          <>
            <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>{selectedAccountIds.length}件選択中</span>
            <button className="btn btn-ghost btn-sm" onClick={selectAll}>全選択</button>
            <button className="btn btn-ghost btn-sm" onClick={clearSelection}>選択解除</button>
            <button className="btn btn-danger btn-sm" onClick={handleDelete}>削除</button>
          </>
        )}
      </div>

      {/* アカウント一覧テーブル */}
      <div style={{ flex: 1, overflow: 'auto', padding: '0 20px' }}>
        {filteredAccounts.length === 0 ? (
          <div className="empty-state" style={{ paddingTop: 80 }}>
            <h3>アカウントがありません</h3>
            <p>「+ 追加」ボタンまたはCSVインポートでアカウントを追加してください</p>
          </div>
        ) : (
          <table>
            <thead>
              <tr>
                <th style={{ width: 40 }}><input type="checkbox" checked={selectedAccountIds.length === filteredAccounts.length && filteredAccounts.length > 0} onChange={() => selectedAccountIds.length === filteredAccounts.length ? clearSelection() : selectAll()} /></th>
                <th>ユーザー名</th>
                <th>ステータス</th>
                <th>グループ</th>
                <th>プロキシ</th>
                <th>最終確認</th>
                <th style={{ width: 160 }}>操作</th>
              </tr>
            </thead>
            <tbody>
              {filteredAccounts.map(account => (
                <tr key={account.id} style={{ cursor: 'pointer' }}>
                  <td><input type="checkbox" checked={selectedAccountIds.includes(account.id)} onChange={(e) => selectAccount(account.id, true)} /></td>
                  <td>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <div style={{ width: 32, height: 32, borderRadius: '50%', background: 'var(--bg-hover)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 14, fontWeight: 700, color: 'var(--accent)' }}>
                        {account.username.charAt(0).toUpperCase()}
                      </div>
                      <div>
                        <div style={{ fontWeight: 600 }}>{account.displayName}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>@{account.username}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span className={`badge badge-${account.status === 'active' ? 'success' : account.status === 'locked' ? 'warning' : account.status === 'suspended' ? 'danger' : 'info'}`}>
                      <span className={`status-dot ${account.status}`} />
                      {ACCOUNT_STATUS_LABELS[account.status] || account.status}
                    </span>
                  </td>
                  <td>
                    <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                      {account.groupIds.map(gid => {
                        const group = groups.find(g => g.id === gid);
                        return group ? <span key={gid} style={{ fontSize: 11, padding: '1px 6px', borderRadius: 8, background: `${group.color}20`, color: group.color }}>{group.name}</span> : null;
                      })}
                    </div>
                  </td>
                  <td>
                    {account.proxy?.enabled ? (
                      <span style={{ fontSize: 12, color: 'var(--text-accent)' }}>{account.proxy.protocol}://{account.proxy.host}:{account.proxy.port}</span>
                    ) : (
                      <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>なし</span>
                    )}
                  </td>
                  <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                    {account.lastChecked ? new Date(account.lastChecked).toLocaleString('ja-JP') : '-'}
                  </td>
                  <td>
                    <div style={{ display: 'flex', gap: 4 }}>
                      <button className="btn btn-primary btn-sm" onClick={() => handleOpenSession(account.id)}>ログイン</button>
                      <button className="btn btn-ghost btn-sm" onClick={() => { setEditingAccount(account); openModal('account-form'); }}>編集</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* フッター（件数表示） */}
      <div style={{ padding: '8px 20px', borderTop: '1px solid var(--border-color)', display: 'flex', alignItems: 'center', gap: 12, fontSize: 12, color: 'var(--text-muted)' }}>
        <span>{filteredAccounts.length}件 / 全{accounts.length}件</span>
      </div>

      {/* モーダル */}
      {modalOpen === 'account-form' && <AccountForm account={editingAccount} onSave={handleSave} onClose={closeModal} />}
      {modalOpen === 'group-manager' && <GroupManager onClose={closeModal} />}
    </div>
  );
};
