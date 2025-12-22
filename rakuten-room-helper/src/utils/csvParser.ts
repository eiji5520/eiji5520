import Papa from 'papaparse';
import { v4 as uuidv4 } from 'uuid';
import type { Product } from '../types';

/**
 * CSVテキストをパースして商品データに変換
 */
export function parseCsvToProducts(csvText: string): {
  products: Product[];
  errors: string[];
} {
  const errors: string[] = [];
  const products: Product[] = [];

  const result = Papa.parse<Record<string, string>>(csvText, {
    header: true,
    skipEmptyLines: true,
    transformHeader: (header) => header.trim().toLowerCase(),
  });

  if (result.errors.length > 0) {
    for (const error of result.errors) {
      errors.push(`行 ${error.row}: ${error.message}`);
    }
  }

  for (let i = 0; i < result.data.length; i++) {
    const row = result.data[i];
    const rowNum = i + 2; // ヘッダー行 + 0始まりインデックス

    // 必須フィールドのチェック
    const title = row.title || row['商品名'] || row['タイトル'] || '';
    const url = row.url || row['URL'] || row['商品URL'] || '';

    if (!title.trim()) {
      errors.push(`行 ${rowNum}: 商品名が空です`);
      continue;
    }

    if (!url.trim()) {
      errors.push(`行 ${rowNum}: URLが空です`);
      continue;
    }

    // URLの簡易バリデーション
    if (!isValidUrl(url)) {
      errors.push(`行 ${rowNum}: URLの形式が不正です: ${url}`);
      continue;
    }

    // 価格のパース
    const priceStr = row.price || row['価格'] || row['金額'] || '';
    const price = priceStr ? parsePrice(priceStr) : undefined;

    // タグのパース
    const tagsStr = row.tags || row['タグ'] || '';
    const tags = tagsStr
      ? tagsStr.split(/[,、]/).map((t) => t.trim()).filter(Boolean)
      : [];

    const now = new Date().toISOString();
    products.push({
      id: uuidv4(),
      title: title.trim(),
      url: url.trim(),
      price,
      shop: (row.shop || row['ショップ'] || row['店舗'] || '').trim() || undefined,
      imageUrl: (row.imageurl || row['画像URL'] || row['image'] || '').trim() || undefined,
      memo: (row.memo || row['メモ'] || row['備考'] || '').trim() || undefined,
      tags,
      createdAt: now,
      updatedAt: now,
    });
  }

  return { products, errors };
}

/**
 * 商品データをCSV形式に変換
 */
export function productsToCSv(products: Product[]): string {
  const data = products.map((p) => ({
    title: p.title,
    url: p.url,
    price: p.price?.toString() || '',
    shop: p.shop || '',
    imageUrl: p.imageUrl || '',
    memo: p.memo || '',
    tags: p.tags.join(','),
  }));

  return Papa.unparse(data, {
    header: true,
  });
}

/**
 * 投稿データをCSV形式に変換
 */
export function postsToCSv(
  posts: { content: string; productTitle: string; productUrl: string; status: string; createdAt: string }[]
): string {
  return Papa.unparse(posts, {
    header: true,
  });
}

/**
 * URLの簡易バリデーション
 */
function isValidUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
}

/**
 * 価格文字列を数値にパース
 */
function parsePrice(priceStr: string): number | undefined {
  // 数字以外を除去
  const numStr = priceStr.replace(/[^\d]/g, '');
  const num = parseInt(numStr, 10);
  return isNaN(num) ? undefined : num;
}

/**
 * サンプルCSVテンプレート
 */
export const SAMPLE_CSV_TEMPLATE = `title,url,price,shop,imageUrl,memo,tags
商品名サンプル,https://item.rakuten.co.jp/xxx/yyy,1980,ショップ名,,メモ欄,タグ1,タグ2`;
