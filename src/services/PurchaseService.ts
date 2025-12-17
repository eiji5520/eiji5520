import { Capacitor } from '@capacitor/core';
import 'cordova-plugin-purchase';
import { STORAGE_KEYS } from '../types';

const REMOVE_ADS_ID = import.meta.env.VITE_IAP_REMOVE_ADS_ID || 'remove_ads';

// cordova-plugin-purchase の型定義
declare global {
  interface Window {
    CdvPurchase: typeof CdvPurchase;
  }
}

type PurchaseCallback = (adsRemoved: boolean) => void;

class PurchaseService {
  private initialized = false;
  private store: typeof CdvPurchase.store | null = null;
  private callbacks: PurchaseCallback[] = [];
  private _adsRemoved = false;

  constructor() {
    // localStorageから初期状態を読み込む
    this.loadFromStorage();
  }

  /**
   * localStorageから購入状態を読み込む
   */
  private loadFromStorage(): void {
    try {
      const stored = localStorage.getItem(STORAGE_KEYS.ADS_REMOVED);
      this._adsRemoved = stored === 'true';
    } catch (error) {
      console.error('[PurchaseService] Failed to load from storage:', error);
    }
  }

  /**
   * localStorageに購入状態を保存
   */
  private saveToStorage(): void {
    try {
      localStorage.setItem(STORAGE_KEYS.ADS_REMOVED, String(this._adsRemoved));
    } catch (error) {
      console.error('[PurchaseService] Failed to save to storage:', error);
    }
  }

  /**
   * 購入状態変更のコールバックを登録
   */
  onPurchaseUpdate(callback: PurchaseCallback): () => void {
    this.callbacks.push(callback);
    // 現在の状態を即座に通知
    callback(this._adsRemoved);
    // 解除関数を返す
    return () => {
      const index = this.callbacks.indexOf(callback);
      if (index > -1) {
        this.callbacks.splice(index, 1);
      }
    };
  }

  /**
   * 全コールバックに通知
   */
  private notifyCallbacks(): void {
    this.callbacks.forEach(cb => cb(this._adsRemoved));
  }

  /**
   * 広告削除済みかどうか
   */
  get adsRemoved(): boolean {
    return this._adsRemoved;
  }

  /**
   * 広告削除済み状態を設定（内部用）
   */
  private setAdsRemoved(value: boolean): void {
    if (this._adsRemoved !== value) {
      this._adsRemoved = value;
      this.saveToStorage();
      this.notifyCallbacks();
    }
  }

  /**
   * IAPの初期化
   */
  async initialize(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
      console.log('[PurchaseService] Not running on native platform, skipping initialization');
      return;
    }

    if (this.initialized) {
      console.log('[PurchaseService] Already initialized');
      return;
    }

    try {
      // cordova-plugin-purchaseが読み込まれるまで待機
      await this.waitForStore();

      this.store = window.CdvPurchase.store;

      // デバッグログを有効化（開発時）
      if (import.meta.env.VITE_ENV !== 'production') {
        this.store.verbosity = window.CdvPurchase.LogLevel.DEBUG;
      }

      // 商品を登録
      this.store.register([
        {
          id: REMOVE_ADS_ID,
          type: window.CdvPurchase.ProductType.NON_CONSUMABLE,
          platform: window.CdvPurchase.Platform.GOOGLE_PLAY,
        },
      ]);

      // 購入承認時のハンドラ
      this.store.when()
        .productUpdated((product) => {
          console.log('[PurchaseService] Product updated:', product);
        })
        .approved((transaction) => {
          console.log('[PurchaseService] Purchase approved:', transaction);
          // 購入を完了（finish）する
          transaction.finish();
        })
        .finished((transaction) => {
          console.log('[PurchaseService] Purchase finished:', transaction);
          // 購入完了後、所有状態を更新
          this.checkOwnership();
        })
        .verified((receipt) => {
          console.log('[PurchaseService] Receipt verified:', receipt);
          receipt.finish();
        });

      // エラーハンドラ
      this.store.error((error) => {
        console.error('[PurchaseService] Store error:', error);
      });

      // ストアを初期化
      await this.store.initialize([window.CdvPurchase.Platform.GOOGLE_PLAY]);

      // 所有状態をチェック
      this.checkOwnership();

      this.initialized = true;
      console.log('[PurchaseService] Initialized successfully');
    } catch (error) {
      console.error('[PurchaseService] Initialization failed:', error);
      throw error;
    }
  }

  /**
   * CdvPurchaseが利用可能になるまで待機
   */
  private waitForStore(): Promise<void> {
    return new Promise((resolve, reject) => {
      const maxAttempts = 50;
      let attempts = 0;

      const check = () => {
        if (window.CdvPurchase && window.CdvPurchase.store) {
          resolve();
        } else if (attempts >= maxAttempts) {
          reject(new Error('CdvPurchase not available'));
        } else {
          attempts++;
          setTimeout(check, 100);
        }
      };

      check();
    });
  }

  /**
   * 商品の所有状態をチェック
   */
  private checkOwnership(): void {
    if (!this.store) return;

    const product = this.store.get(REMOVE_ADS_ID, window.CdvPurchase.Platform.GOOGLE_PLAY);
    if (product && product.owned) {
      console.log('[PurchaseService] User owns remove_ads');
      this.setAdsRemoved(true);
    }
  }

  /**
   * 商品情報を取得
   */
  getProduct(): CdvPurchase.Product | undefined {
    if (!this.store) return undefined;
    return this.store.get(REMOVE_ADS_ID, window.CdvPurchase.Platform.GOOGLE_PLAY);
  }

  /**
   * 商品の価格を取得
   */
  getPrice(): string | null {
    const product = this.getProduct();
    if (product && product.pricing) {
      return product.pricing.price;
    }
    return null;
  }

  /**
   * 購入を開始
   */
  async purchase(): Promise<boolean> {
    if (!Capacitor.isNativePlatform()) {
      console.log('[PurchaseService] Cannot purchase: not on native platform');
      // 開発用: テスト購入として成功させる
      if (import.meta.env.VITE_ENV !== 'production') {
        this.setAdsRemoved(true);
        return true;
      }
      return false;
    }

    if (!this.store) {
      throw new Error('Store not initialized');
    }

    const product = this.getProduct();
    if (!product) {
      throw new Error('Product not found');
    }

    if (product.owned) {
      console.log('[PurchaseService] Product already owned');
      this.setAdsRemoved(true);
      return true;
    }

    try {
      const offer = product.getOffer();
      if (!offer) {
        throw new Error('No offer available');
      }

      const result = await offer.order();
      if (result && result.isError) {
        throw new Error(result.message || 'Purchase failed');
      }

      return true;
    } catch (error) {
      console.error('[PurchaseService] Purchase failed:', error);
      throw error;
    }
  }

  /**
   * 購入を復元
   */
  async restore(): Promise<boolean> {
    if (!Capacitor.isNativePlatform()) {
      console.log('[PurchaseService] Cannot restore: not on native platform');
      return false;
    }

    if (!this.store) {
      throw new Error('Store not initialized');
    }

    try {
      await this.store.restorePurchases();
      // 復元後、所有状態をチェック
      this.checkOwnership();
      return this._adsRemoved;
    } catch (error) {
      console.error('[PurchaseService] Restore failed:', error);
      throw error;
    }
  }

  /**
   * 購入状態をリセット（デバッグ用）
   */
  resetForTesting(): void {
    if (import.meta.env.VITE_ENV !== 'production') {
      this.setAdsRemoved(false);
      console.log('[PurchaseService] Purchase state reset for testing');
    }
  }
}

export const purchaseService = new PurchaseService();
