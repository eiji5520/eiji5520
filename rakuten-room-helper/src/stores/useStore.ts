import { create } from 'zustand';
import { v4 as uuidv4 } from 'uuid';
import type {
  Product,
  Post,
  PostStatus,
  TemplateType,
  AppSettings,
  FilterCondition,
  SortCondition,
  GeneratedContent,
} from '../types';
import { productStorage, postStorage, settingsStorage } from '../utils/storage';

interface AppState {
  // 商品データ
  products: Product[];
  selectedProductId: string | null;

  // 投稿データ
  posts: Post[];
  selectedPostId: string | null;

  // 生成された候補（一時的）
  generatedContents: GeneratedContent[];
  isGenerating: boolean;

  // フィルタ・ソート
  filter: FilterCondition;
  sort: SortCondition;

  // 設定
  settings: AppSettings;

  // UI状態
  activeTab: 'input' | 'generate' | 'manage' | 'settings';

  // アクション: 商品
  addProduct: (product: Omit<Product, 'id' | 'createdAt' | 'updatedAt'>) => Product;
  addProducts: (products: Omit<Product, 'id' | 'createdAt' | 'updatedAt'>[]) => Product[];
  updateProduct: (id: string, updates: Partial<Product>) => void;
  deleteProduct: (id: string) => void;
  selectProduct: (id: string | null) => void;
  checkDuplicate: (url: string) => Product | null;

  // アクション: 投稿
  addPost: (productId: string, content: string, templateType: TemplateType) => Post;
  updatePost: (id: string, updates: Partial<Post>) => void;
  deletePost: (id: string) => void;
  selectPost: (id: string | null) => void;
  updatePostStatus: (id: string, status: PostStatus) => void;

  // アクション: 生成
  setGeneratedContents: (contents: GeneratedContent[]) => void;
  clearGeneratedContents: () => void;
  setIsGenerating: (isGenerating: boolean) => void;

  // アクション: フィルタ・ソート
  setFilter: (filter: Partial<FilterCondition>) => void;
  clearFilter: () => void;
  setSort: (sort: SortCondition) => void;

  // アクション: 設定
  updateSettings: (settings: Partial<AppSettings>) => void;

  // アクション: UI
  setActiveTab: (tab: AppState['activeTab']) => void;

  // アクション: 初期化・リセット
  initialize: () => void;
  reset: () => void;
}

export const useStore = create<AppState>((set, get) => ({
  // 初期状態
  products: [],
  selectedProductId: null,
  posts: [],
  selectedPostId: null,
  generatedContents: [],
  isGenerating: false,
  filter: {},
  sort: { field: 'createdAt', direction: 'desc' },
  settings: {
    apiKey: '',
    defaultTemplate: 'simple',
    maxGenerations: 3,
  },
  activeTab: 'input',

  // 商品アクション
  addProduct: (productData) => {
    const now = new Date().toISOString();
    const product: Product = {
      ...productData,
      id: uuidv4(),
      createdAt: now,
      updatedAt: now,
    };
    set((state) => {
      const newProducts = [...state.products, product];
      productStorage.save(newProducts);
      return { products: newProducts };
    });
    return product;
  },

  addProducts: (productsData) => {
    const now = new Date().toISOString();
    const newProducts: Product[] = productsData.map((p) => ({
      ...p,
      id: uuidv4(),
      createdAt: now,
      updatedAt: now,
    }));
    set((state) => {
      const allProducts = [...state.products, ...newProducts];
      productStorage.save(allProducts);
      return { products: allProducts };
    });
    return newProducts;
  },

  updateProduct: (id, updates) => {
    set((state) => {
      const newProducts = state.products.map((p) =>
        p.id === id ? { ...p, ...updates, updatedAt: new Date().toISOString() } : p
      );
      productStorage.save(newProducts);
      return { products: newProducts };
    });
  },

  deleteProduct: (id) => {
    set((state) => {
      const newProducts = state.products.filter((p) => p.id !== id);
      productStorage.save(newProducts);
      // 関連する投稿も削除
      const newPosts = state.posts.filter((p) => p.productId !== id);
      postStorage.save(newPosts);
      return {
        products: newProducts,
        posts: newPosts,
        selectedProductId: state.selectedProductId === id ? null : state.selectedProductId,
      };
    });
  },

  selectProduct: (id) => {
    set({ selectedProductId: id });
  },

  checkDuplicate: (url) => {
    const { products } = get();
    return products.find((p) => p.url === url) || null;
  },

  // 投稿アクション
  addPost: (productId, content, templateType) => {
    const now = new Date().toISOString();
    const post: Post = {
      id: uuidv4(),
      productId,
      content,
      templateType,
      status: 'pending',
      ngWarnings: [],
      createdAt: now,
      updatedAt: now,
    };
    set((state) => {
      const newPosts = [...state.posts, post];
      postStorage.save(newPosts);
      return { posts: newPosts };
    });
    return post;
  },

  updatePost: (id, updates) => {
    set((state) => {
      const newPosts = state.posts.map((p) =>
        p.id === id ? { ...p, ...updates, updatedAt: new Date().toISOString() } : p
      );
      postStorage.save(newPosts);
      return { posts: newPosts };
    });
  },

  deletePost: (id) => {
    set((state) => {
      const newPosts = state.posts.filter((p) => p.id !== id);
      postStorage.save(newPosts);
      return {
        posts: newPosts,
        selectedPostId: state.selectedPostId === id ? null : state.selectedPostId,
      };
    });
  },

  selectPost: (id) => {
    set({ selectedPostId: id });
  },

  updatePostStatus: (id, status) => {
    set((state) => {
      const now = new Date().toISOString();
      const newPosts = state.posts.map((p) =>
        p.id === id
          ? {
              ...p,
              status,
              postedAt: status === 'posted' ? now : p.postedAt,
              updatedAt: now,
            }
          : p
      );
      postStorage.save(newPosts);
      return { posts: newPosts };
    });
  },

  // 生成アクション
  setGeneratedContents: (contents) => {
    set({ generatedContents: contents });
  },

  clearGeneratedContents: () => {
    set({ generatedContents: [] });
  },

  setIsGenerating: (isGenerating) => {
    set({ isGenerating });
  },

  // フィルタ・ソートアクション
  setFilter: (filter) => {
    set((state) => ({ filter: { ...state.filter, ...filter } }));
  },

  clearFilter: () => {
    set({ filter: {} });
  },

  setSort: (sort) => {
    set({ sort });
  },

  // 設定アクション
  updateSettings: (updates) => {
    set((state) => {
      const newSettings = { ...state.settings, ...updates };
      settingsStorage.save(newSettings);
      return { settings: newSettings };
    });
  },

  // UI アクション
  setActiveTab: (tab) => {
    set({ activeTab: tab });
  },

  // 初期化
  initialize: () => {
    const products = productStorage.load();
    const posts = postStorage.load();
    const settings = settingsStorage.load();
    set({ products, posts, settings });
  },

  // リセット
  reset: () => {
    productStorage.clear();
    postStorage.clear();
    settingsStorage.clear();
    set({
      products: [],
      posts: [],
      settings: {
        apiKey: '',
        defaultTemplate: 'simple',
        maxGenerations: 3,
      },
      selectedProductId: null,
      selectedPostId: null,
      generatedContents: [],
      filter: {},
    });
  },
}));

/**
 * フィルタリングされた投稿を取得するセレクタ
 */
export function useFilteredPosts() {
  const { posts, products, filter, sort } = useStore();

  let filtered = [...posts];

  // ステータスフィルタ
  if (filter.status) {
    filtered = filtered.filter((p) => p.status === filter.status);
  }

  // テンプレートタイプフィルタ
  if (filter.templateType) {
    filtered = filtered.filter((p) => p.templateType === filter.templateType);
  }

  // 警告ありフィルタ
  if (filter.hasWarnings !== undefined) {
    filtered = filtered.filter((p) =>
      filter.hasWarnings ? p.ngWarnings.length > 0 : p.ngWarnings.length === 0
    );
  }

  // 検索フィルタ
  if (filter.searchQuery) {
    const query = filter.searchQuery.toLowerCase();
    filtered = filtered.filter((p) => {
      const product = products.find((prod) => prod.id === p.productId);
      return (
        p.content.toLowerCase().includes(query) ||
        product?.title.toLowerCase().includes(query)
      );
    });
  }

  // 日付フィルタ
  if (filter.dateFrom) {
    filtered = filtered.filter((p) => p.createdAt >= filter.dateFrom!);
  }
  if (filter.dateTo) {
    filtered = filtered.filter((p) => p.createdAt <= filter.dateTo!);
  }

  // ソート
  filtered.sort((a, b) => {
    let comparison = 0;
    switch (sort.field) {
      case 'createdAt':
        comparison = a.createdAt.localeCompare(b.createdAt);
        break;
      case 'updatedAt':
        comparison = a.updatedAt.localeCompare(b.updatedAt);
        break;
      case 'status':
        comparison = a.status.localeCompare(b.status);
        break;
      case 'title': {
        const productA = products.find((p) => p.id === a.productId);
        const productB = products.find((p) => p.id === b.productId);
        comparison = (productA?.title || '').localeCompare(productB?.title || '');
        break;
      }
    }
    return sort.direction === 'asc' ? comparison : -comparison;
  });

  return filtered;
}
