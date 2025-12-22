import { describe, it, expect } from 'vitest';
import { checkNgWords, hasNgWords, hasErrorNgWords } from './ngWordChecker';

describe('ngWordChecker', () => {
  describe('checkNgWords', () => {
    it('誇大表現を検出する', () => {
      const result = checkNgWords('この商品は絶対に効きます！');
      expect(result.length).toBeGreaterThan(0);
      expect(result[0].type).toBe('exaggeration');
    });

    it('医療効果の表現を検出する', () => {
      const result = checkNgWords('この商品で病気が治ります');
      expect(result.length).toBeGreaterThan(0);
      expect(result.some((w) => w.type === 'medical_claim')).toBe(true);
    });

    it('価格保証の表現を検出する', () => {
      const result = checkNgWords('最安値保証！どこよりも安い');
      expect(result.length).toBeGreaterThan(0);
      expect(result.some((w) => w.type === 'price_guarantee')).toBe(true);
    });

    it('他社批判の表現を検出する', () => {
      const result = checkNgWords('他社はダメですがうちは違います');
      expect(result.length).toBeGreaterThan(0);
      expect(result.some((w) => w.type === 'competitor_criticism')).toBe(true);
    });

    it('正常な文章ではNGワードを検出しない', () => {
      const result = checkNgWords('この商品は便利で使いやすいです。おすすめ！');
      expect(result.length).toBe(0);
    });

    it('複数のNGワードを検出する', () => {
      const result = checkNgWords('絶対に効く最安値の商品です');
      expect(result.length).toBeGreaterThan(1);
    });

    it('重複したNGワードは1回だけカウントする', () => {
      const result = checkNgWords('絶対に効く、本当に絶対に効く');
      const absoluteWords = result.filter((w) => w.word.includes('絶対'));
      expect(absoluteWords.length).toBe(1);
    });
  });

  describe('hasNgWords', () => {
    it('NGワードがある場合はtrueを返す', () => {
      expect(hasNgWords('絶対に効く')).toBe(true);
    });

    it('NGワードがない場合はfalseを返す', () => {
      expect(hasNgWords('おすすめの商品です')).toBe(false);
    });
  });

  describe('hasErrorNgWords', () => {
    it('エラーレベルのNGワードがある場合はtrueを返す', () => {
      expect(hasErrorNgWords('病気が治ります')).toBe(true);
    });

    it('警告レベルのみの場合はfalseを返す', () => {
      expect(hasErrorNgWords('世界一の商品')).toBe(false);
    });

    it('NGワードがない場合はfalseを返す', () => {
      expect(hasErrorNgWords('普通の商品紹介')).toBe(false);
    });
  });
});
