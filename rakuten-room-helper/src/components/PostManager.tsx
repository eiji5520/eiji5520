import { useState } from 'react';
import { useStore, useFilteredPosts } from '../stores/useStore';
import { getTemplateLabel } from '../lib/templates';
import { NG_TYPE_LABELS } from '../utils/ngWordChecker';
import { postsToCSv } from '../utils/csvParser';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import type { PostStatus, TemplateType } from '../types';
import {
  Copy,
  Check,
  Trash2,
  Download,
  Filter,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Calendar,
  Search,
  X,
} from 'lucide-react';

const STATUS_LABELS: Record<PostStatus, string> = {
  pending: '未投稿',
  posted: '投稿済',
  scheduled: '予約済',
};

const STATUS_COLORS: Record<PostStatus, string> = {
  pending: 'bg-gray-100 text-gray-700',
  posted: 'bg-green-100 text-green-700',
  scheduled: 'bg-blue-100 text-blue-700',
};

export default function PostManager() {
  const {
    products,
    posts,
    filter,
    setFilter,
    clearFilter,
    sort,
    setSort,
    updatePostStatus,
    deletePost,
    setActiveTab,
  } = useStore();

  const filteredPosts = useFilteredPosts();

  const [copiedId, setCopiedId] = useState<string | null>(null);
  const [showFilters, setShowFilters] = useState(false);

  // クリップボードにコピー
  const handleCopy = async (postId: string, content: string) => {
    try {
      await navigator.clipboard.writeText(content);
      setCopiedId(postId);
      setTimeout(() => setCopiedId(null), 2000);
    } catch {
      console.error('コピーに失敗しました');
    }
  };

  // ステータス更新
  const handleStatusChange = (postId: string, status: PostStatus) => {
    updatePostStatus(postId, status);
  };

  // CSVエクスポート
  const handleExportCsv = () => {
    const data = filteredPosts.map((post) => {
      const product = products.find((p) => p.id === post.productId);
      return {
        content: post.content,
        productTitle: product?.title || '',
        productUrl: product?.url || '',
        status: STATUS_LABELS[post.status],
        createdAt: post.createdAt,
      };
    });

    const csv = postsToCSv(data);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `rakuten-room-posts-${format(new Date(), 'yyyyMMdd-HHmmss')}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  // 商品情報を取得
  const getProduct = (productId: string) => products.find((p) => p.id === productId);

  // フィルタがアクティブかどうか
  const hasActiveFilters =
    filter.status || filter.templateType || filter.searchQuery || filter.hasWarnings !== undefined;

  if (posts.length === 0) {
    return (
      <div className="card text-center py-12">
        <p className="text-gray-500 mb-4">保存された投稿文がありません</p>
        <button
          onClick={() => setActiveTab('generate')}
          className="text-rakuten-red hover:underline"
        >
          投稿文を生成する
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* ツールバー */}
      <div className="card">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-bold">投稿管理</h2>
            <span className="text-sm text-gray-500">
              ({filteredPosts.length}/{posts.length}件)
            </span>
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => setShowFilters(!showFilters)}
              className={`btn-secondary flex items-center gap-1 text-sm ${
                hasActiveFilters ? 'bg-rakuten-red text-white hover:bg-rakuten-dark' : ''
              }`}
            >
              <Filter className="w-4 h-4" />
              フィルタ
            </button>
            <button
              onClick={handleExportCsv}
              className="btn-secondary flex items-center gap-1 text-sm"
            >
              <Download className="w-4 h-4" />
              CSV出力
            </button>
          </div>
        </div>

        {/* フィルタパネル */}
        {showFilters && (
          <div className="mt-4 pt-4 border-t">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  検索
                </label>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="text"
                    value={filter.searchQuery || ''}
                    onChange={(e) => setFilter({ searchQuery: e.target.value || undefined })}
                    className="input-field pl-9"
                    placeholder="キーワード検索..."
                  />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  ステータス
                </label>
                <select
                  value={filter.status || ''}
                  onChange={(e) =>
                    setFilter({ status: (e.target.value as PostStatus) || undefined })
                  }
                  className="input-field"
                >
                  <option value="">すべて</option>
                  <option value="pending">未投稿</option>
                  <option value="posted">投稿済</option>
                  <option value="scheduled">予約済</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  テンプレート
                </label>
                <select
                  value={filter.templateType || ''}
                  onChange={(e) =>
                    setFilter({ templateType: (e.target.value as TemplateType) || undefined })
                  }
                  className="input-field"
                >
                  <option value="">すべて</option>
                  <option value="simple">シンプル</option>
                  <option value="comparison">比較</option>
                  <option value="time_saving">時短</option>
                  <option value="gift">ギフト</option>
                  <option value="cost_performance">コスパ</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  警告
                </label>
                <select
                  value={filter.hasWarnings === undefined ? '' : filter.hasWarnings ? 'yes' : 'no'}
                  onChange={(e) =>
                    setFilter({
                      hasWarnings:
                        e.target.value === '' ? undefined : e.target.value === 'yes',
                    })
                  }
                  className="input-field"
                >
                  <option value="">すべて</option>
                  <option value="yes">警告あり</option>
                  <option value="no">警告なし</option>
                </select>
              </div>
            </div>
            <div className="flex items-center justify-between mt-4">
              <div className="flex items-center gap-4">
                <label className="text-sm text-gray-600">並び順:</label>
                <select
                  value={`${sort.field}-${sort.direction}`}
                  onChange={(e) => {
                    const [field, direction] = e.target.value.split('-') as [
                      typeof sort.field,
                      typeof sort.direction
                    ];
                    setSort({ field, direction });
                  }}
                  className="input-field w-auto"
                >
                  <option value="createdAt-desc">作成日時（新しい順）</option>
                  <option value="createdAt-asc">作成日時（古い順）</option>
                  <option value="updatedAt-desc">更新日時（新しい順）</option>
                  <option value="title-asc">商品名（A-Z）</option>
                  <option value="status-asc">ステータス</option>
                </select>
              </div>
              {hasActiveFilters && (
                <button
                  onClick={clearFilter}
                  className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1"
                >
                  <X className="w-4 h-4" />
                  フィルタをクリア
                </button>
              )}
            </div>
          </div>
        )}
      </div>

      {/* 投稿一覧 */}
      <div className="space-y-4">
        {filteredPosts.map((post) => {
          const product = getProduct(post.productId);

          return (
            <div key={post.id} className="card">
              <div className="flex items-start justify-between gap-4 mb-3">
                <div>
                  <h3 className="font-medium text-gray-900">
                    {product?.title || '商品不明'}
                  </h3>
                  <div className="flex items-center gap-2 mt-1 text-sm text-gray-500">
                    <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[post.status]}`}>
                      {STATUS_LABELS[post.status]}
                    </span>
                    <span className="text-xs">{getTemplateLabel(post.templateType)}</span>
                    <span className="flex items-center gap-1 text-xs">
                      <Calendar className="w-3 h-3" />
                      {format(new Date(post.createdAt), 'yyyy/MM/dd HH:mm', { locale: ja })}
                    </span>
                  </div>
                </div>
                <div className="flex gap-2 flex-shrink-0">
                  <button
                    onClick={() => handleCopy(post.id, post.content)}
                    className="btn-secondary flex items-center gap-1 text-sm py-1"
                  >
                    {copiedId === post.id ? (
                      <>
                        <Check className="w-3 h-3" />
                        コピー済
                      </>
                    ) : (
                      <>
                        <Copy className="w-3 h-3" />
                        コピー
                      </>
                    )}
                  </button>
                  <button
                    onClick={() => deletePost(post.id)}
                    className="p-2 text-gray-400 hover:text-red-500 transition-colors"
                    title="削除"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div className="p-3 bg-gray-50 rounded-lg">
                <p className="whitespace-pre-wrap text-gray-800 text-sm">
                  {post.content}
                </p>
                <p className="text-xs text-gray-400 mt-2">{post.content.length}文字</p>
              </div>

              {post.ngWarnings.length > 0 && (
                <div className="mt-3 p-3 bg-amber-50 rounded-lg">
                  <div className="flex items-start gap-2">
                    <AlertTriangle className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-amber-800">注意表現あり</p>
                      <ul className="mt-1 space-y-0.5">
                        {post.ngWarnings.map((warning, i) => (
                          <li key={i} className="text-xs text-amber-700">
                            [{NG_TYPE_LABELS[warning.type]}] 「{warning.word}」
                          </li>
                        ))}
                      </ul>
                    </div>
                  </div>
                </div>
              )}

              {/* ステータス変更ボタン */}
              <div className="mt-4 flex items-center gap-2">
                <span className="text-sm text-gray-500">ステータス:</span>
                <div className="flex gap-1">
                  <button
                    onClick={() => handleStatusChange(post.id, 'pending')}
                    className={`px-3 py-1 rounded text-sm flex items-center gap-1 ${
                      post.status === 'pending'
                        ? 'bg-gray-200 text-gray-800'
                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                    }`}
                  >
                    <Clock className="w-3 h-3" />
                    未投稿
                  </button>
                  <button
                    onClick={() => handleStatusChange(post.id, 'posted')}
                    className={`px-3 py-1 rounded text-sm flex items-center gap-1 ${
                      post.status === 'posted'
                        ? 'bg-green-200 text-green-800'
                        : 'bg-gray-100 text-gray-500 hover:bg-green-100'
                    }`}
                  >
                    <CheckCircle2 className="w-3 h-3" />
                    投稿済
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {filteredPosts.length === 0 && hasActiveFilters && (
        <div className="card text-center py-8 text-gray-500">
          <p>条件に一致する投稿がありません</p>
          <button
            onClick={clearFilter}
            className="mt-2 text-rakuten-red hover:underline"
          >
            フィルタをクリア
          </button>
        </div>
      )}
    </div>
  );
}
