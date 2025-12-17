import { useEffect } from 'react';
import { adService } from '../services/AdService';

interface BannerAdProps {
  adsRemoved: boolean;
}

export function BannerAd({ adsRemoved }: BannerAdProps) {
  useEffect(() => {
    if (adsRemoved) {
      // 広告削除済みの場合はバナーを削除
      adService.removeBanner();
      return;
    }

    // 広告を表示
    adService.initialize().then(() => {
      adService.showBanner();
    });

    // コンポーネントのアンマウント時にバナーを非表示
    return () => {
      adService.hideBanner();
    };
  }, [adsRemoved]);

  // このコンポーネント自体は何もレンダリングしない
  // バナー広告はネイティブUIとして画面下部に表示される
  return null;
}
