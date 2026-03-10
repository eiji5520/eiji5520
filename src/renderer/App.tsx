import React, { useEffect } from 'react';
import { useUIStore, useAccountStore, useGroupStore } from './store';
import { Sidebar } from './components/sidebar/Sidebar';
import { AccountsPage } from './pages/AccountsPage';
import { BulkOperationsPage } from './pages/BulkOperationsPage';
import { BanCheckPage } from './pages/BanCheckPage';
import { NotificationsPage } from './pages/NotificationsPage';
import { AnalysisPage } from './pages/AnalysisPage';
import { SettingsPage } from './pages/SettingsPage';
import { LogsPage } from './pages/LogsPage';

export const App: React.FC = () => {
  const { currentPage, theme } = useUIStore();
  const loadAccounts = useAccountStore(s => s.loadAccounts);
  const loadGroups = useGroupStore(s => s.loadGroups);

  useEffect(() => {
    loadAccounts();
    loadGroups();

    // バックグラウンドイベントリスナー
    const cleanup = window.xboard.onBackgroundEvent((event) => {
      console.log('バックグラウンドイベント:', event);
      if (event.type === 'status_change') {
        loadAccounts();
      }
    });

    return cleanup;
  }, []);

  const renderPage = () => {
    switch (currentPage) {
      case 'accounts': return <AccountsPage />;
      case 'bulk': return <BulkOperationsPage />;
      case 'bancheck': return <BanCheckPage />;
      case 'notifications': return <NotificationsPage />;
      case 'analysis': return <AnalysisPage />;
      case 'settings': return <SettingsPage />;
      case 'logs': return <LogsPage />;
      default: return <AccountsPage />;
    }
  };

  return (
    <div className="app-layout" data-theme={theme}>
      <Sidebar />
      <div className="main-content">
        {renderPage()}
      </div>
    </div>
  );
};
