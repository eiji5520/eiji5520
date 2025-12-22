import { useState } from 'react';
import { useStore } from '../stores/useStore';
import { testApiKey } from '../lib/claude';
import { TEMPLATE_CONFIGS } from '../lib/templates';
import { exportAllData } from '../utils/storage';
import type { TemplateType } from '../types';
import {
  Key,
  CheckCircle,
  XCircle,
  Loader2,
  Download,
  Trash2,
  AlertTriangle,
} from 'lucide-react';

export default function Settings() {
  const { settings, updateSettings, reset, products, posts } = useStore();

  const [apiKey, setApiKey] = useState(settings.apiKey);
  const [isTestingKey, setIsTestingKey] = useState(false);
  const [keyTestResult, setKeyTestResult] = useState<'success' | 'error' | null>(null);
  const [showConfirmReset, setShowConfirmReset] = useState(false);

  // APIキーを保存
  const handleSaveApiKey = async () => {
    updateSettings({ apiKey });
    setKeyTestResult(null);
  };

  // APIキーをテスト
  const handleTestApiKey = async () => {
    if (!apiKey) {
      setKeyTestResult('error');
      return;
    }

    setIsTestingKey(true);
    setKeyTestResult(null);

    try {
      const isValid = await testApiKey(apiKey);
      setKeyTestResult(isValid ? 'success' : 'error');
    } catch {
      setKeyTestResult('error');
    } finally {
      setIsTestingKey(false);
    }
  };

  // データエクスポート
  const handleExport = () => {
    const data = exportAllData();
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `rakuten-room-helper-backup-${new Date().toISOString().slice(0, 10)}.json`;
    link.click();
    URL.revokeObjectURL(url);
  };

  // 全データリセット
  const handleReset = () => {
    reset();
    setApiKey('');
    setKeyTestResult(null);
    setShowConfirmReset(false);
  };

  return (
    <div className="space-y-6">
      {/* APIキー設定 */}
      <div className="card">
        <h2 className="text-lg font-bold mb-4 flex items-center gap-2">
          <Key className="w-5 h-5" />
          Claude APIキー
        </h2>
        <p className="text-sm text-gray-600 mb-4">
          投稿文の生成にはClaude APIキーが必要です。
          <a
            href="https://console.anthropic.com/account/keys"
            target="_blank"
            rel="noopener noreferrer"
            className="text-rakuten-red hover:underline ml-1"
          >
            APIキーを取得
          </a>
        </p>

        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              APIキー
            </label>
            <input
              type="password"
              value={apiKey}
              onChange={(e) => setApiKey(e.target.value)}
              className="input-field font-mono"
              placeholder="sk-ant-api03-..."
            />
          </div>

          <div className="flex gap-2">
            <button
              onClick={handleSaveApiKey}
              className="btn-primary"
              disabled={!apiKey || apiKey === settings.apiKey}
            >
              保存
            </button>
            <button
              onClick={handleTestApiKey}
              className="btn-secondary flex items-center gap-2"
              disabled={!apiKey || isTestingKey}
            >
              {isTestingKey ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  テスト中...
                </>
              ) : (
                'テスト'
              )}
            </button>
          </div>

          {keyTestResult && (
            <div
              className={`flex items-center gap-2 p-3 rounded-lg ${
                keyTestResult === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
              }`}
            >
              {keyTestResult === 'success' ? (
                <>
                  <CheckCircle className="w-4 h-4" />
                  APIキーは有効です
                </>
              ) : (
                <>
                  <XCircle className="w-4 h-4" />
                  APIキーが無効です
                </>
              )}
            </div>
          )}
        </div>

        <div className="mt-4 p-3 bg-amber-50 rounded-lg">
          <p className="text-sm text-amber-800">
            <AlertTriangle className="w-4 h-4 inline mr-1" />
            APIキーはブラウザのローカルストレージに保存されます。
            共有PCでは使用後にクリアすることを推奨します。
          </p>
        </div>
      </div>

      {/* 生成設定 */}
      <div className="card">
        <h2 className="text-lg font-bold mb-4">生成設定</h2>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              デフォルトテンプレート
            </label>
            <select
              value={settings.defaultTemplate}
              onChange={(e) =>
                updateSettings({ defaultTemplate: e.target.value as TemplateType })
              }
              className="input-field"
            >
              {TEMPLATE_CONFIGS.map((config) => (
                <option key={config.type} value={config.type}>
                  {config.label} - {config.description}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              生成する案の数
            </label>
            <select
              value={settings.maxGenerations}
              onChange={(e) =>
                updateSettings({ maxGenerations: parseInt(e.target.value, 10) })
              }
              className="input-field w-32"
            >
              <option value={1}>1案</option>
              <option value={2}>2案</option>
              <option value={3}>3案</option>
              <option value={5}>5案</option>
            </select>
            <p className="text-xs text-gray-500 mt-1">
              案の数が多いほどAPIコストがかかります
            </p>
          </div>
        </div>
      </div>

      {/* データ管理 */}
      <div className="card">
        <h2 className="text-lg font-bold mb-4">データ管理</h2>
        <div className="space-y-4">
          <div className="p-3 bg-gray-50 rounded-lg">
            <p className="text-sm text-gray-600">
              保存データ: 商品 {products.length}件 / 投稿文 {posts.length}件
            </p>
          </div>

          <div className="flex gap-2">
            <button
              onClick={handleExport}
              className="btn-secondary flex items-center gap-2"
            >
              <Download className="w-4 h-4" />
              バックアップ（JSON）
            </button>
          </div>

          <hr />

          <div>
            <h3 className="text-sm font-medium text-gray-700 mb-2">危険な操作</h3>
            {showConfirmReset ? (
              <div className="p-4 bg-red-50 rounded-lg">
                <p className="text-sm text-red-700 mb-3">
                  すべてのデータ（商品、投稿文、設定）が削除されます。この操作は取り消せません。
                </p>
                <div className="flex gap-2">
                  <button onClick={handleReset} className="btn-primary bg-red-600 hover:bg-red-700">
                    削除する
                  </button>
                  <button
                    onClick={() => setShowConfirmReset(false)}
                    className="btn-secondary"
                  >
                    キャンセル
                  </button>
                </div>
              </div>
            ) : (
              <button
                onClick={() => setShowConfirmReset(true)}
                className="btn-secondary text-red-600 border-red-200 hover:bg-red-50 flex items-center gap-2"
              >
                <Trash2 className="w-4 h-4" />
                全データを削除
              </button>
            )}
          </div>
        </div>
      </div>

      {/* 使い方 */}
      <div className="card">
        <h2 className="text-lg font-bold mb-4">使い方</h2>
        <ol className="list-decimal list-inside space-y-2 text-sm text-gray-600">
          <li>「商品入力」タブで商品情報を登録（手動 or CSV）</li>
          <li>「投稿文生成」タブで商品を選択し、テンプレートを選んで生成</li>
          <li>生成された文面をコピーして、楽天ROOMで手動投稿</li>
          <li>「管理」タブで投稿状況を記録・管理</li>
        </ol>
        <div className="mt-4 p-3 bg-blue-50 rounded-lg">
          <p className="text-sm text-blue-800">
            このツールは投稿文の生成と管理を支援します。
            実際の楽天ROOMへの投稿は手動で行ってください。
          </p>
        </div>
      </div>
    </div>
  );
}
