// ============================================================
// XBoard - Zustand ストア
// グローバル状態管理
// ============================================================

import { create } from 'zustand';
import {
  Account,
  AccountGroup,
  OperationLog,
  NotificationData,
  BanCheckResult,
  AppSettings,
  BulkOperationRequest,
} from '../../shared/types';
import { DEFAULT_SETTINGS } from '../../shared/constants';

// ============================================================
// アカウントストア
// ============================================================

interface AccountStore {
  accounts: Account[];
  selectedAccountIds: string[];
  filterGroupId: string | null;
  searchQuery: string;
  loading: boolean;

  loadAccounts: () => Promise<void>;
  addAccount: (data: Partial<Account>) => Promise<Account>;
  updateAccount: (data: Partial<Account> & { id: string }) => Promise<void>;
  deleteAccount: (id: string) => Promise<void>;
  selectAccount: (id: string, multi?: boolean) => void;
  selectAll: () => void;
  clearSelection: () => void;
  setFilterGroup: (groupId: string | null) => void;
  setSearchQuery: (query: string) => void;
  importCSV: () => Promise<{ imported: number; skipped: number; errors: string[] }>;
  exportCSV: () => Promise<void>;
}

export const useAccountStore = create<AccountStore>((set, get) => ({
  accounts: [],
  selectedAccountIds: [],
  filterGroupId: null,
  searchQuery: '',
  loading: false,

  loadAccounts: async () => {
    set({ loading: true });
    const accounts = await window.xboard.getAccounts();
    set({ accounts, loading: false });
  },

  addAccount: async (data) => {
    const account = await window.xboard.addAccount(data);
    set(s => ({ accounts: [account, ...s.accounts] }));
    return account;
  },

  updateAccount: async (data) => {
    await window.xboard.updateAccount(data);
    set(s => ({
      accounts: s.accounts.map(a => a.id === data.id ? { ...a, ...data } : a),
    }));
  },

  deleteAccount: async (id) => {
    await window.xboard.deleteAccount(id);
    set(s => ({
      accounts: s.accounts.filter(a => a.id !== id),
      selectedAccountIds: s.selectedAccountIds.filter(i => i !== id),
    }));
  },

  selectAccount: (id, multi = false) => {
    set(s => {
      if (multi) {
        const selected = s.selectedAccountIds.includes(id)
          ? s.selectedAccountIds.filter(i => i !== id)
          : [...s.selectedAccountIds, id];
        return { selectedAccountIds: selected };
      }
      return { selectedAccountIds: [id] };
    });
  },

  selectAll: () => {
    set(s => ({ selectedAccountIds: s.accounts.map(a => a.id) }));
  },

  clearSelection: () => set({ selectedAccountIds: [] }),

  setFilterGroup: (groupId) => set({ filterGroupId: groupId }),

  setSearchQuery: (query) => set({ searchQuery: query }),

  importCSV: async () => {
    const result = await window.xboard.importAccountsCSV();
    if (result.imported > 0) {
      await get().loadAccounts();
    }
    return result;
  },

  exportCSV: async () => {
    await window.xboard.exportAccountsCSV();
  },
}));

// ============================================================
// グループストア
// ============================================================

interface GroupStore {
  groups: AccountGroup[];
  loadGroups: () => Promise<void>;
  addGroup: (data: Partial<AccountGroup>) => Promise<AccountGroup>;
  updateGroup: (data: Partial<AccountGroup> & { id: string }) => Promise<void>;
  deleteGroup: (id: string) => Promise<void>;
}

export const useGroupStore = create<GroupStore>((set) => ({
  groups: [],

  loadGroups: async () => {
    const groups = await window.xboard.getGroups();
    set({ groups });
  },

  addGroup: async (data) => {
    const group = await window.xboard.addGroup(data);
    set(s => ({ groups: [...s.groups, group] }));
    return group;
  },

  updateGroup: async (data) => {
    await window.xboard.updateGroup(data);
    set(s => ({
      groups: s.groups.map(g => g.id === data.id ? { ...g, ...data } : g),
    }));
  },

  deleteGroup: async (id) => {
    await window.xboard.deleteGroup(id);
    set(s => ({ groups: s.groups.filter(g => g.id !== id) }));
  },
}));

// ============================================================
// UIストア
// ============================================================

type Page = 'accounts' | 'browser' | 'bulk' | 'notifications' | 'bancheck' | 'analysis' | 'settings' | 'logs';

interface UIStore {
  currentPage: Page;
  theme: 'dark' | 'light';
  sidebarCollapsed: boolean;
  modalOpen: string | null;

  setPage: (page: Page) => void;
  setTheme: (theme: 'dark' | 'light') => void;
  toggleSidebar: () => void;
  openModal: (modalId: string) => void;
  closeModal: () => void;
}

export const useUIStore = create<UIStore>((set) => ({
  currentPage: 'accounts',
  theme: 'dark',
  sidebarCollapsed: false,
  modalOpen: null,

  setPage: (page) => set({ currentPage: page }),
  setTheme: (theme) => set({ theme }),
  toggleSidebar: () => set(s => ({ sidebarCollapsed: !s.sidebarCollapsed })),
  openModal: (modalId) => set({ modalOpen: modalId }),
  closeModal: () => set({ modalOpen: null }),
}));

// ============================================================
// 操作ログストア
// ============================================================

interface LogStore {
  logs: OperationLog[];
  loadLogs: (limit?: number, accountId?: string) => Promise<void>;
  exportLogs: () => Promise<void>;
}

export const useLogStore = create<LogStore>((set) => ({
  logs: [],

  loadLogs: async (limit, accountId) => {
    const logs = await window.xboard.getLogs(limit, accountId);
    set({ logs });
  },

  exportLogs: async () => {
    await window.xboard.exportLogs();
  },
}));

// ============================================================
// 通知ストア
// ============================================================

interface NotificationStore {
  notifications: NotificationData[];
  unreadCount: number;
  loadNotifications: (accountId?: string) => Promise<void>;
}

export const useNotificationStore = create<NotificationStore>((set) => ({
  notifications: [],
  unreadCount: 0,

  loadNotifications: async (accountId) => {
    const notifications = await window.xboard.fetchNotifications(accountId);
    const unreadCount = notifications.filter((n: NotificationData) => !n.read).length;
    set({ notifications, unreadCount });
  },
}));

// ============================================================
// 一括操作ストア
// ============================================================

interface BulkStore {
  isExecuting: boolean;
  progress: { total: number; completed: number; succeeded: number; failed: number } | null;

  executeBulk: (request: BulkOperationRequest) => Promise<void>;
  resetProgress: () => void;
}

export const useBulkStore = create<BulkStore>((set) => ({
  isExecuting: false,
  progress: null,

  executeBulk: async (request) => {
    set({ isExecuting: true, progress: { total: request.accountIds.length, completed: 0, succeeded: 0, failed: 0 } });
    try {
      await window.xboard.executeBulk(request);
    } finally {
      set({ isExecuting: false });
    }
  },

  resetProgress: () => set({ progress: null }),
}));
