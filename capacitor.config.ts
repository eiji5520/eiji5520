import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'jp.co.uchida.sudoku',
  appName: 'Sudoku',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
  },
  plugins: {
    // AdMob設定
    AdMob: {
      // Android用AdMob App ID (AndroidManifest.xmlにも設定が必要)
      // テスト用ID: ca-app-pub-3940256099942544~3347511713
      // 本番用IDは .env.production で管理
    },
  },
  android: {
    // ステータスバーの色
    backgroundColor: '#4a90d9',
  },
};

export default config;
