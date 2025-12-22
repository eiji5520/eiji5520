/**
 * 商品情報の型定義
 */
export interface Product {
  id: string;
  title: string;
  url: string;
  price?: number;
  shop?: string;
  imageUrl?: string;
  memo?: string;
  tags: string[];
  createdAt: string;
  updatedAt: string;
}

/**
 * 投稿のステータス
 */
export type PostStatus = 'pending' | 'posted' | 'scheduled';

/**
 * 投稿文のテンプレートタイプ
 */
export type TemplateType = 'simple' | 'comparison' | 'time_saving' | 'gift' | 'cost_performance';

/**
 * 投稿データの型定義
 */
export interface Post {
  id: string;
  productId: string;
  content: string;
  templateType: TemplateType;
  status: PostStatus;
  scheduledAt?: string;
  postedAt?: string;
  ngWarnings: NgWarning[];
  createdAt: string;
  updatedAt: string;
}

/**
 * NGワード警告の型定義
 */
export interface NgWarning {
  type: 'exaggeration' | 'medical_claim' | 'price_guarantee' | 'competitor_criticism' | 'other';
  word: string;
  message: string;
  severity: 'warning' | 'error';
}

/**
 * 生成された投稿文候補
 */
export interface GeneratedContent {
  id: string;
  content: string;
  templateType: TemplateType;
  ngWarnings: NgWarning[];
}

/**
 * CSV インポート用の行データ
 */
export interface CsvRow {
  title: string;
  url: string;
  price?: string;
  shop?: string;
  imageUrl?: string;
  memo?: string;
  tags?: string;
}

/**
 * テンプレートの設定
 */
export interface TemplateConfig {
  type: TemplateType;
  label: string;
  description: string;
  prompt: string;
}

/**
 * アプリケーションの設定
 */
export interface AppSettings {
  apiKey: string;
  defaultTemplate: TemplateType;
  maxGenerations: number;
}

/**
 * フィルタ条件
 */
export interface FilterCondition {
  status?: PostStatus;
  templateType?: TemplateType;
  searchQuery?: string;
  dateFrom?: string;
  dateTo?: string;
  hasWarnings?: boolean;
}

/**
 * ソート条件
 */
export interface SortCondition {
  field: 'createdAt' | 'updatedAt' | 'title' | 'status';
  direction: 'asc' | 'desc';
}
