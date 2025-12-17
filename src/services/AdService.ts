import { AdMob, BannerAdSize, BannerAdPosition } from '@capacitor-community/admob';
import type { BannerAdOptions } from '@capacitor-community/admob';
import { Capacitor } from '@capacitor/core';

const BANNER_ID = import.meta.env.VITE_ADMOB_BANNER_ID || 'ca-app-pub-3940256099942544/6300978111';
const IS_TESTING = import.meta.env.VITE_ENV !== 'production';

class AdService {
  private initialized = false;
  private bannerShowing = false;

  /**
   * AdMobの初期化
   */
  async initialize(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
      console.log('[AdService] Not running on native platform, skipping initialization');
      return;
    }

    if (this.initialized) {
      console.log('[AdService] Already initialized');
      return;
    }

    try {
      await AdMob.initialize({
        // テスト用デバイスIDを設定（実機テスト時に必要）
        // initializeForTesting: IS_TESTING,
      });

      // テストモード設定
      if (IS_TESTING) {
        console.log('[AdService] Running in test mode');
      }

      this.initialized = true;
      console.log('[AdService] Initialized successfully');
    } catch (error) {
      console.error('[AdService] Initialization failed:', error);
      throw error;
    }
  }

  /**
   * バナー広告を表示
   */
  async showBanner(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
      console.log('[AdService] Banner not shown: not on native platform');
      return;
    }

    if (!this.initialized) {
      await this.initialize();
    }

    if (this.bannerShowing) {
      console.log('[AdService] Banner already showing');
      return;
    }

    try {
      const options: BannerAdOptions = {
        adId: BANNER_ID,
        adSize: BannerAdSize.BANNER,
        position: BannerAdPosition.BOTTOM_CENTER,
        margin: 0,
        isTesting: IS_TESTING,
      };

      await AdMob.showBanner(options);
      this.bannerShowing = true;
      console.log('[AdService] Banner shown successfully');
    } catch (error) {
      console.error('[AdService] Failed to show banner:', error);
      throw error;
    }
  }

  /**
   * バナー広告を非表示
   */
  async hideBanner(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
      return;
    }

    if (!this.bannerShowing) {
      return;
    }

    try {
      await AdMob.hideBanner();
      this.bannerShowing = false;
      console.log('[AdService] Banner hidden successfully');
    } catch (error) {
      console.error('[AdService] Failed to hide banner:', error);
      throw error;
    }
  }

  /**
   * バナー広告を削除（完全に削除）
   */
  async removeBanner(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
      return;
    }

    try {
      await AdMob.removeBanner();
      this.bannerShowing = false;
      console.log('[AdService] Banner removed successfully');
    } catch (error) {
      console.error('[AdService] Failed to remove banner:', error);
      throw error;
    }
  }

  /**
   * バナーが表示中かどうか
   */
  isBannerShowing(): boolean {
    return this.bannerShowing;
  }
}

export const adService = new AdService();
