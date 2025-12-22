import Anthropic from '@anthropic-ai/sdk';
import type { Product, TemplateType, GeneratedContent } from '../types';
import { getTemplatePrompt } from './templates';
import { checkNgWords } from '../utils/ngWordChecker';
import { v4 as uuidv4 } from 'uuid';

/**
 * Claude APIクライアントを作成
 * APIキーはユーザーが環境変数または設定画面から提供
 */
function createClient(apiKey: string): Anthropic {
  return new Anthropic({
    apiKey,
    dangerouslyAllowBrowser: true, // ブラウザから直接APIを呼び出す（MVPのため）
  });
}

/**
 * 商品情報を投稿文生成用のテキストに変換
 */
function formatProductInfo(product: Product): string {
  let info = `【商品情報】
商品名: ${product.title}
URL: ${product.url}`;

  if (product.price) {
    info += `\n価格: ${product.price.toLocaleString()}円`;
  }
  if (product.shop) {
    info += `\nショップ: ${product.shop}`;
  }
  if (product.memo) {
    info += `\nメモ: ${product.memo}`;
  }
  if (product.tags.length > 0) {
    info += `\nタグ: ${product.tags.join(', ')}`;
  }

  return info;
}

/**
 * 投稿文を生成する
 */
export async function generatePostContent(
  apiKey: string,
  product: Product,
  templateType: TemplateType,
  count: number = 3
): Promise<GeneratedContent[]> {
  if (!apiKey) {
    throw new Error('APIキーが設定されていません');
  }

  const client = createClient(apiKey);
  const templatePrompt = getTemplatePrompt(templateType);
  const productInfo = formatProductInfo(product);

  const results: GeneratedContent[] = [];

  // 複数案を生成（並列実行）
  const promises = Array.from({ length: count }, async (_, i) => {
    try {
      const response = await client.messages.create({
        model: 'claude-sonnet-4-20250514',
        max_tokens: 500,
        messages: [
          {
            role: 'user',
            content: `${templatePrompt}

${productInfo}

案${i + 1}を生成してください。他の案とは異なる切り口で。`,
          },
        ],
      });

      const content =
        response.content[0].type === 'text' ? response.content[0].text : '';

      // 生成されたテキストに対してNGワードチェック
      const ngWarnings = checkNgWords(content);

      return {
        id: uuidv4(),
        content: content.trim(),
        templateType,
        ngWarnings,
      };
    } catch (error) {
      console.error(`生成エラー (案${i + 1}):`, error);
      throw error;
    }
  });

  const settledResults = await Promise.allSettled(promises);

  for (const result of settledResults) {
    if (result.status === 'fulfilled') {
      results.push(result.value);
    }
  }

  if (results.length === 0) {
    throw new Error('投稿文の生成に失敗しました');
  }

  return results;
}

/**
 * APIキーの有効性をテスト
 */
export async function testApiKey(apiKey: string): Promise<boolean> {
  if (!apiKey) {
    return false;
  }

  try {
    const client = createClient(apiKey);
    await client.messages.create({
      model: 'claude-sonnet-4-20250514',
      max_tokens: 10,
      messages: [{ role: 'user', content: 'test' }],
    });
    return true;
  } catch {
    return false;
  }
}
