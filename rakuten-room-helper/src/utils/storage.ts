import type { Product, Post, AppSettings } from '../types';

const STORAGE_KEYS = {
  PRODUCTS: 'rakuten-room-helper:products',
  POSTS: 'rakuten-room-helper:posts',
  SETTINGS: 'rakuten-room-helper:settings',
} as const;

/**
 * ローカルストレージからデータを読み込む
 */
function loadFromStorage<T>(key: string, defaultValue: T): T {
  try {
    const stored = localStorage.getItem(key);
    if (stored) {
      return JSON.parse(stored) as T;
    }
  } catch (error) {
    console.error(`Failed to load ${key} from storage:`, error);
  }
  return defaultValue;
}

/**
 * ローカルストレージにデータを保存する
 */
function saveToStorage<T>(key: string, data: T): void {
  try {
    localStorage.setItem(key, JSON.stringify(data));
  } catch (error) {
    console.error(`Failed to save ${key} to storage:`, error);
  }
}

/**
 * 商品データの操作
 */
export const productStorage = {
  load: (): Product[] => loadFromStorage<Product[]>(STORAGE_KEYS.PRODUCTS, []),
  save: (products: Product[]): void => saveToStorage(STORAGE_KEYS.PRODUCTS, products),
  clear: (): void => localStorage.removeItem(STORAGE_KEYS.PRODUCTS),
};

/**
 * 投稿データの操作
 */
export const postStorage = {
  load: (): Post[] => loadFromStorage<Post[]>(STORAGE_KEYS.POSTS, []),
  save: (posts: Post[]): void => saveToStorage(STORAGE_KEYS.POSTS, posts),
  clear: (): void => localStorage.removeItem(STORAGE_KEYS.POSTS),
};

/**
 * 設定データの操作
 */
export const settingsStorage = {
  load: (): AppSettings =>
    loadFromStorage<AppSettings>(STORAGE_KEYS.SETTINGS, {
      apiKey: '',
      defaultTemplate: 'simple',
      maxGenerations: 3,
    }),
  save: (settings: AppSettings): void => saveToStorage(STORAGE_KEYS.SETTINGS, settings),
  clear: (): void => localStorage.removeItem(STORAGE_KEYS.SETTINGS),
};

/**
 * 全データをクリア
 */
export function clearAllStorage(): void {
  productStorage.clear();
  postStorage.clear();
  settingsStorage.clear();
}

/**
 * 全データをエクスポート
 */
export function exportAllData(): {
  products: Product[];
  posts: Post[];
  settings: AppSettings;
  exportedAt: string;
} {
  return {
    products: productStorage.load(),
    posts: postStorage.load(),
    settings: settingsStorage.load(),
    exportedAt: new Date().toISOString(),
  };
}

/**
 * データをインポート
 */
export function importAllData(data: {
  products?: Product[];
  posts?: Post[];
  settings?: AppSettings;
}): void {
  if (data.products) {
    productStorage.save(data.products);
  }
  if (data.posts) {
    postStorage.save(data.posts);
  }
  if (data.settings) {
    settingsStorage.save(data.settings);
  }
}
