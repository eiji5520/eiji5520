import { describe, it, expect } from 'vitest';
import { parseCsvToProducts, productsToCSv } from './csvParser';

describe('csvParser', () => {
  describe('parseCsvToProducts', () => {
    it('正常なCSVをパースできる', () => {
      const csv = `title,url,price,shop,memo,tags
テスト商品,https://example.com/item1,1980,テストショップ,メモ,タグ1、タグ2`;

      const { products, errors } = parseCsvToProducts(csv);

      expect(errors.length).toBe(0);
      expect(products.length).toBe(1);
      expect(products[0].title).toBe('テスト商品');
      expect(products[0].url).toBe('https://example.com/item1');
      expect(products[0].price).toBe(1980);
      expect(products[0].shop).toBe('テストショップ');
    });

    it('日本語ヘッダーでもパースできる', () => {
      const csv = `商品名,URL,価格,ショップ
テスト商品,https://example.com/item1,1980,テストショップ`;

      const { products, errors } = parseCsvToProducts(csv);

      expect(errors.length).toBe(0);
      expect(products.length).toBe(1);
      expect(products[0].title).toBe('テスト商品');
    });

    it('必須フィールドがない場合はエラーを返す', () => {
      const csv = `title,url
,https://example.com
商品名,`;

      const { products, errors } = parseCsvToProducts(csv);

      expect(errors.length).toBe(2);
      expect(products.length).toBe(0);
    });

    it('無効なURLの場合はエラーを返す', () => {
      const csv = `title,url
商品名,invalid-url`;

      const { products, errors } = parseCsvToProducts(csv);

      expect(errors.length).toBe(1);
      expect(products.length).toBe(0);
    });

    it('タグをカンマ区切りでパースできる', () => {
      const csv = `title,url,tags
商品名,https://example.com,タグ1、タグ2、タグ3`;

      const { products } = parseCsvToProducts(csv);

      expect(products[0].tags).toEqual(['タグ1', 'タグ2', 'タグ3']);
    });

    it('価格を数値に変換する', () => {
      const csv = `title,url,price
商品名,https://example.com,¥1980`;

      const { products } = parseCsvToProducts(csv);

      expect(products[0].price).toBe(1980);
    });
  });

  describe('productsToCSv', () => {
    it('商品データをCSVに変換できる', () => {
      const products = [
        {
          id: '1',
          title: 'テスト商品',
          url: 'https://example.com',
          price: 1980,
          shop: 'ショップ',
          tags: ['タグ1', 'タグ2'],
          createdAt: '2024-01-01',
          updatedAt: '2024-01-01',
        },
      ];

      const csv = productsToCSv(products);

      expect(csv).toContain('テスト商品');
      expect(csv).toContain('https://example.com');
      expect(csv).toContain('1980');
    });
  });
});
