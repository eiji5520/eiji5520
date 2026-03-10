// ============================================================
// XBoard - 設定ページ
// テーマ、言語、チェック間隔、暗号化、同期など
// ============================================================

import React, { useState, useEffect } from 'react';
import { useUIStore } from '../store';
import { AppSettings } from '../../shared/types';
import { DEFAULT_SETTINGS } from '../../shared/constants';

export const SettingsPage: React.FC = () => {
  const { theme, setTheme } = useUIStore();
  const [settings, setSettings] = useState<AppSettings>(DEFAULT_SETTINGS);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    window.xboard.getSettings().then((s: AppSettings) => {
      setSettings(s);
      setTheme(s.theme);
    });
  }, []);

  const handleSave = async () => {
    await window.xboard.updateSettings(settings);
    setTheme(settings.theme);
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  };

  const update = (partial: Partial<AppSettings>) => {
    setSettings(prev => ({ ...prev, ...partial }));
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <div style={{ padding: '12px 20px', borderBottom: '1px solid var(--border-color)', display: 'flex', alignItems: 'center' }}>
        <h1 style={{ fontSize: 20, fontWeight: 700, flex: 1 }}>設定</h1>
        <button className="btn btn-primary" onClick={handleSave}>
          {saved ? '保存しました' : '保存'}
        </button>
      </div>

      <div style={{ flex: 1, overflow: 'auto', padding: 20, maxWidth: 600 }}>
        {/* テーマ */}
        <section className="card" style={{ marginBottom: 16 }}>
          <h3 style={{ fontSize: 15, fontWeight: 600, marginBottom: 12 }}>外観</h3>
          <div className="form-group">
            <label>テーマ</label>
            <div style={{ display: 'flex', gap: 8 }}>
              <button className={`btn ${settings.theme === 'dark' ? 'btn-primary' : 'btn-ghost'}`} onClick={() => update({ theme: 'dark' })}>
                ダークモード
              </button>
              <button className={`btn ${settings.theme === 'light' ? 'btn-primary' : 'btn-ghost'}`} onClick={() => update({ theme: 'light' })}>
                ライトモード
              </button>
            </div>
          </div>
          <div className="form-group">
            <label>言語</label>
            <select className="select" value={settings.language} onChange={e => update({ language: e.target.value as any })}>
              <option value="ja">日本語</option>
              <option value="en">English</option>
            </select>
          </div>
        </section>

        {/* 監視設定 */}
        <section className="card" style={{ marginBottom: 16 }}>
          <h3 style={{ fontSize: 15, fontWeight: 600, marginBottom: 12 }}>監視設定</h3>
          <div className="form-group">
            <label>ログイン状態チェック間隔（分）</label>
            <input className="input" type="number" value={settings.checkInterval} onChange={e => update({ checkInterval: parseInt(e.target.value) || 30 })} min={1} max={120} style={{ maxWidth: 120 }} />
          </div>
          <div className="form-group">
            <label>通知チェック間隔（分）</label>
            <input className="input" type="number" value={settings.notificationInterval} onChange={e => update({ notificationInterval: parseInt(e.target.value) || 5 })} min={1} max={60} style={{ maxWidth: 120 }} />
          </div>
          <div className="form-group">
            <label>デフォルト操作遅延（ミリ秒）</label>
            <input className="input" type="number" value={settings.defaultDelay} onChange={e => update({ defaultDelay: parseInt(e.target.value) || 2000 })} min={500} max={30000} style={{ maxWidth: 160 }} />
          </div>
        </section>

        {/* セキュリティ */}
        <section className="card" style={{ marginBottom: 16 }}>
          <h3 style={{ fontSize: 15, fontWeight: 600, marginBottom: 12 }}>セキュリティ</h3>
          <div className="form-group">
            <label className="checkbox-wrapper">
              <input type="checkbox" checked={settings.encryptionEnabled} onChange={e => update({ encryptionEnabled: e.target.checked })} />
              <span>ローカルデータの暗号化を有効にする</span>
            </label>
            <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4, marginLeft: 24 }}>Cookie、認証トークン、プロキシパスワードなどが AES-256-GCM で暗号化されます</div>
          </div>
        </section>

        {/* 同期設定 */}
        <section className="card" style={{ marginBottom: 16 }}>
          <h3 style={{ fontSize: 15, fontWeight: 600, marginBottom: 12 }}>データ同期</h3>
          <div className="form-group">
            <label className="checkbox-wrapper">
              <input type="checkbox" checked={settings.syncEnabled} onChange={e => update({ syncEnabled: e.target.checked })} />
              <span>複数端末間のデータ同期を有効にする</span>
            </label>
          </div>
          {settings.syncEnabled && (
            <div className="form-group">
              <label>同期フォルダパス</label>
              <input className="input" value={settings.syncPath || ''} onChange={e => update({ syncPath: e.target.value })} placeholder="例: /Users/name/Dropbox/XBoard" />
              <div style={{ fontSize: 11, color: 'var(--text-muted)', marginTop: 4 }}>Dropbox、Google Drive、OneDrive などのクラウドストレージフォルダを指定</div>
            </div>
          )}
        </section>

        {/* バージョン情報 */}
        <section className="card">
          <h3 style={{ fontSize: 15, fontWeight: 600, marginBottom: 8 }}>XBoard について</h3>
          <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
            <div>バージョン: 1.0.0</div>
            <div>プラットフォーム: {navigator.platform}</div>
            <div>Electron + React + TypeScript</div>
          </div>
        </section>
      </div>
    </div>
  );
};
