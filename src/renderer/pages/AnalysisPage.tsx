// ============================================================
// XBoard - データ分析ページ
// ポスト取得、ソート、メディアダウンロード
// ============================================================

import React, { useState } from 'react';
import { useAccountStore } from '../store';
import { PostData } from '../../shared/types';

type SortKey = 'likeCount' | 'repostCount' | 'replyCount' | 'impressionCount' | 'timestamp';

const SORT_LABELS: Record<SortKey, string> = {
  likeCount: 'いいね数',
  repostCount: 'リポスト数',
  replyCount: 'リプライ数',
  impressionCount: 'インプレッション数',
  timestamp: '投稿日時',
};

export const AnalysisPage: React.FC = () => {
  const accounts = useAccountStore(s => s.accounts);
  const [selectedAccountId, setSelectedAccountId] = useState('');
  const [targetUsername, setTargetUsername] = useState('');
  const [posts, setPosts] = useState<PostData[]>([]);
  const [loading, setLoading] = useState(false);
  const [sortKey, setSortKey] = useState<SortKey>('likeCount');
  const [sortAsc, setSortAsc] = useState(false);

  const handleFetch = async () => {
    if (!selectedAccountId || !targetUsername) return;
    setLoading(true);
    try {
      const result = await window.xboard.fetchPosts(selectedAccountId, targetUsername.replace(/^@/, ''));
      setPosts(result || []);
    } catch (err) {
      console.error('ポスト取得エラー:', err);
    } finally {
      setLoading(false);
    }
  };

  const sortedPosts = [...posts].sort((a, b) => {
    const aVal = a[sortKey] ?? 0;
    const bVal = b[sortKey] ?? 0;
    return sortAsc ? (aVal as number) - (bVal as number) : (bVal as number) - (aVal as number);
  });

  const handleDownloadMedia = async (url: string, type: string) => {
    try {
      await window.xboard.downloadMedia(url, type);
    } catch (err) {
      console.error('ダウンロードエラー:', err);
    }
  };

  const activeAccounts = accounts.filter(a => a.status === 'active');

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <div style={{ padding: '12px 20px', borderBottom: '1px solid var(--border-color)' }}>
        <h1 style={{ fontSize: 20, fontWeight: 700 }}>データ分析</h1>
      </div>

      {/* 検索フォーム */}
      <div style={{ padding: '12px 20px', borderBottom: '1px solid var(--border-color)', display: 'flex', alignItems: 'flex-end', gap: 12 }}>
        <div className="form-group" style={{ marginBottom: 0 }}>
          <label>使用アカウント</label>
          <select className="select" style={{ width: 200 }} value={selectedAccountId} onChange={e => setSelectedAccountId(e.target.value)}>
            <option value="">選択...</option>
            {activeAccounts.map(a => <option key={a.id} value={a.id}>@{a.username}</option>)}
          </select>
        </div>
        <div className="form-group" style={{ marginBottom: 0, flex: 1 }}>
          <label>対象ユーザー名</label>
          <input className="input" value={targetUsername} onChange={e => setTargetUsername(e.target.value)} placeholder="@username" />
        </div>
        <button className="btn btn-primary" onClick={handleFetch} disabled={loading || !selectedAccountId || !targetUsername}>
          {loading ? '取得中...' : 'ポスト取得'}
        </button>
      </div>

      {/* ソート・フィルター */}
      {posts.length > 0 && (
        <div style={{ padding: '8px 20px', borderBottom: '1px solid var(--border-color)', display: 'flex', alignItems: 'center', gap: 12, fontSize: 13 }}>
          <span style={{ color: 'var(--text-secondary)' }}>{posts.length}件のポスト</span>
          <div style={{ flex: 1 }} />
          <label style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
            並び替え:
            <select className="select" style={{ width: 160, padding: '4px 8px' }} value={sortKey} onChange={e => setSortKey(e.target.value as SortKey)}>
              {Object.entries(SORT_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </label>
          <button className="btn btn-ghost btn-sm" onClick={() => setSortAsc(!sortAsc)}>
            {sortAsc ? '昇順' : '降順'}
          </button>
        </div>
      )}

      {/* ポスト一覧 */}
      <div style={{ flex: 1, overflow: 'auto', padding: 20 }}>
        {posts.length === 0 ? (
          <div className="empty-state">
            <h3>データ分析</h3>
            <p>アカウントと対象ユーザーを指定してポストを取得してください。<br />
            いいね数・インプレッション数などでソートして分析できます。</p>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {sortedPosts.map(post => (
              <div key={post.id} className="card" style={{ padding: '12px 16px' }}>
                <div style={{ fontSize: 13, marginBottom: 8, lineHeight: 1.6 }}>{post.text}</div>
                <div style={{ display: 'flex', gap: 16, fontSize: 12, color: 'var(--text-secondary)' }}>
                  <span>いいね: {post.likeCount.toLocaleString()}</span>
                  <span>RT: {post.repostCount.toLocaleString()}</span>
                  <span>リプ: {post.replyCount.toLocaleString()}</span>
                  <span>Imp: {post.impressionCount.toLocaleString()}</span>
                  <span style={{ color: 'var(--text-muted)' }}>{new Date(post.timestamp).toLocaleString('ja-JP')}</span>
                  {post.mediaUrls && post.mediaUrls.length > 0 && (
                    <button className="btn btn-ghost btn-sm" onClick={() => handleDownloadMedia(post.mediaUrls![0], 'video')} style={{ padding: '0 6px', fontSize: 11 }}>
                      メディアDL
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};
