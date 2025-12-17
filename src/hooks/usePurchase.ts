import { useState, useEffect, useCallback } from 'react';
import { purchaseService } from '../services/PurchaseService';

export function usePurchase() {
  const [adsRemoved, setAdsRemoved] = useState(purchaseService.adsRemoved);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [price, setPrice] = useState<string | null>(null);

  useEffect(() => {
    // 購入状態の変更を監視
    const unsubscribe = purchaseService.onPurchaseUpdate((removed) => {
      setAdsRemoved(removed);
    });

    // 初期化
    const init = async () => {
      try {
        await purchaseService.initialize();
        const productPrice = purchaseService.getPrice();
        setPrice(productPrice);
      } catch (err) {
        console.error('Failed to initialize purchase service:', err);
      }
    };

    init();

    return unsubscribe;
  }, []);

  /**
   * 購入を開始
   */
  const purchase = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      await purchaseService.purchase();
    } catch (err) {
      const message = err instanceof Error ? err.message : '購入に失敗しました';
      setError(message);
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, []);

  /**
   * 購入を復元
   */
  const restore = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const restored = await purchaseService.restore();
      if (!restored) {
        setError('復元できる購入がありませんでした');
      }
      return restored;
    } catch (err) {
      const message = err instanceof Error ? err.message : '復元に失敗しました';
      setError(message);
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, []);

  /**
   * テスト用: 購入状態をリセット
   */
  const resetForTesting = useCallback(() => {
    purchaseService.resetForTesting();
  }, []);

  return {
    adsRemoved,
    isLoading,
    error,
    price,
    purchase,
    restore,
    resetForTesting,
  };
}
