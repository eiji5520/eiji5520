import React from 'react';
import { useUIStore, useAccountStore, useNotificationStore } from '../../store';
import { ACCOUNT_STATUS_LABELS } from '../../../shared/constants';
import './Sidebar.css';

const NAV_ITEMS = [
  { id: 'accounts' as const, label: 'アカウント管理', icon: '👤' },
  { id: 'bulk' as const, label: '一括操作', icon: '⚡' },
  { id: 'bancheck' as const, label: 'バンチェック', icon: '🔍' },
  { id: 'notifications' as const, label: '通知・DM', icon: '🔔' },
  { id: 'analysis' as const, label: 'データ分析', icon: '📊' },
  { id: 'logs' as const, label: '操作ログ', icon: '📋' },
  { id: 'settings' as const, label: '設定', icon: '⚙️' },
];

export const Sidebar: React.FC = () => {
  const { currentPage, setPage, sidebarCollapsed, toggleSidebar } = useUIStore();
  const accounts = useAccountStore(s => s.accounts);
  const unreadCount = useNotificationStore(s => s.unreadCount);

  const statusCounts = {
    active: accounts.filter(a => a.status === 'active').length,
    locked: accounts.filter(a => a.status === 'locked').length,
    suspended: accounts.filter(a => a.status === 'suspended').length,
    total: accounts.length,
  };

  return (
    <div className={`sidebar ${sidebarCollapsed ? 'collapsed' : ''}`}>
      {/* ヘッダー */}
      <div className="sidebar-header">
        {!sidebarCollapsed && (
          <div className="sidebar-brand">
            <span className="sidebar-logo">X</span>
            <span className="sidebar-title">Board</span>
          </div>
        )}
        <button className="btn-ghost btn-sm sidebar-toggle" onClick={toggleSidebar}>
          {sidebarCollapsed ? '→' : '←'}
        </button>
      </div>

      {/* アカウント概要 */}
      {!sidebarCollapsed && (
        <div className="sidebar-summary">
          <div className="summary-item">
            <span className="summary-count">{statusCounts.total}</span>
            <span className="summary-label">合計</span>
          </div>
          <div className="summary-item">
            <span className="summary-count" style={{ color: 'var(--success)' }}>{statusCounts.active}</span>
            <span className="summary-label">アクティブ</span>
          </div>
          <div className="summary-item">
            <span className="summary-count" style={{ color: 'var(--warning)' }}>{statusCounts.locked}</span>
            <span className="summary-label">ロック</span>
          </div>
          <div className="summary-item">
            <span className="summary-count" style={{ color: 'var(--danger)' }}>{statusCounts.suspended}</span>
            <span className="summary-label">凍結</span>
          </div>
        </div>
      )}

      {/* ナビゲーション */}
      <nav className="sidebar-nav">
        {NAV_ITEMS.map(item => (
          <button
            key={item.id}
            className={`sidebar-nav-item ${currentPage === item.id ? 'active' : ''}`}
            onClick={() => setPage(item.id)}
            title={sidebarCollapsed ? item.label : undefined}
          >
            <span className="nav-icon">{item.icon}</span>
            {!sidebarCollapsed && (
              <>
                <span className="nav-label">{item.label}</span>
                {item.id === 'notifications' && unreadCount > 0 && (
                  <span className="nav-badge">{unreadCount}</span>
                )}
              </>
            )}
          </button>
        ))}
      </nav>

      {/* フッター */}
      {!sidebarCollapsed && (
        <div className="sidebar-footer">
          <span className="sidebar-version">XBoard v1.0.0</span>
        </div>
      )}
    </div>
  );
};
