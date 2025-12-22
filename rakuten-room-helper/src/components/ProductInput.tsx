import { useState, useRef } from 'react';
import { useStore } from '../stores/useStore';
import { parseCsvToProducts, SAMPLE_CSV_TEMPLATE } from '../utils/csvParser';
import {
  Upload,
  Plus,
  Trash2,
  AlertTriangle,
  FileText,
  ExternalLink,
  Copy,
} from 'lucide-react';

export default function ProductInput() {
  const { products, addProduct, addProducts, deleteProduct, checkDuplicate, setActiveTab, selectProduct } =
    useStore();
  const fileInputRef = useRef<HTMLInputElement>(null);

  // フォーム状態
  const [formData, setFormData] = useState({
    title: '',
    url: '',
    price: '',
    shop: '',
    memo: '',
    tags: '',
  });
  const [formError, setFormError] = useState<string | null>(null);
  const [duplicateWarning, setDuplicateWarning] = useState<string | null>(null);

  // CSVインポート状態
  const [csvText, setCsvText] = useState('');
  const [csvErrors, setCsvErrors] = useState<string[]>([]);
  const [showCsvInput, setShowCsvInput] = useState(false);

  // URL変更時に重複チェック
  const handleUrlChange = (url: string) => {
    setFormData((prev) => ({ ...prev, url }));
    if (url) {
      const duplicate = checkDuplicate(url);
      setDuplicateWarning(duplicate ? `この商品は既に登録されています: ${duplicate.title}` : null);
    } else {
      setDuplicateWarning(null);
    }
  };

  // 手動入力で商品追加
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!formData.title.trim()) {
      setFormError('商品名を入力してください');
      return;
    }
    if (!formData.url.trim()) {
      setFormError('URLを入力してください');
      return;
    }

    try {
      new URL(formData.url);
    } catch {
      setFormError('URLの形式が正しくありません');
      return;
    }

    addProduct({
      title: formData.title.trim(),
      url: formData.url.trim(),
      price: formData.price ? parseInt(formData.price.replace(/[^\d]/g, ''), 10) : undefined,
      shop: formData.shop.trim() || undefined,
      memo: formData.memo.trim() || undefined,
      tags: formData.tags
        ? formData.tags.split(/[,、]/).map((t) => t.trim()).filter(Boolean)
        : [],
    });

    // フォームリセット
    setFormData({ title: '', url: '', price: '', shop: '', memo: '', tags: '' });
    setDuplicateWarning(null);
  };

  // CSVインポート
  const handleCsvImport = () => {
    setCsvErrors([]);
    const { products: parsed, errors } = parseCsvToProducts(csvText);

    if (errors.length > 0) {
      setCsvErrors(errors);
    }

    if (parsed.length > 0) {
      // 重複チェック
      const newProducts = parsed.filter((p) => !checkDuplicate(p.url));
      const duplicateCount = parsed.length - newProducts.length;

      if (newProducts.length > 0) {
        addProducts(
          newProducts.map((p) => ({
            title: p.title,
            url: p.url,
            price: p.price,
            shop: p.shop,
            memo: p.memo,
            tags: p.tags,
            imageUrl: p.imageUrl,
          }))
        );
      }

      if (duplicateCount > 0) {
        setCsvErrors((prev) => [...prev, `${duplicateCount}件の重複商品をスキップしました`]);
      }

      if (newProducts.length > 0) {
        setCsvText('');
        setShowCsvInput(false);
      }
    }
  };

  // ファイルからCSV読み込み
  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      const text = event.target?.result as string;
      setCsvText(text);
      setShowCsvInput(true);
    };
    reader.readAsText(file);
  };

  // サンプルCSVをコピー
  const handleCopySample = () => {
    navigator.clipboard.writeText(SAMPLE_CSV_TEMPLATE);
  };

  // 投稿文生成へ
  const handleGeneratePost = (productId: string) => {
    selectProduct(productId);
    setActiveTab('generate');
  };

  return (
    <div className="space-y-6">
      {/* 手動入力フォーム */}
      <div className="card">
        <h2 className="text-lg font-bold mb-4">商品を追加</h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                商品名 <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.title}
                onChange={(e) => setFormData((prev) => ({ ...prev, title: e.target.value }))}
                className="input-field"
                placeholder="商品名を入力"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                URL <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.url}
                onChange={(e) => handleUrlChange(e.target.value)}
                className="input-field"
                placeholder="https://item.rakuten.co.jp/..."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">価格</label>
              <input
                type="text"
                value={formData.price}
                onChange={(e) => setFormData((prev) => ({ ...prev, price: e.target.value }))}
                className="input-field"
                placeholder="1980"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">ショップ名</label>
              <input
                type="text"
                value={formData.shop}
                onChange={(e) => setFormData((prev) => ({ ...prev, shop: e.target.value }))}
                className="input-field"
                placeholder="ショップ名"
              />
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">タグ（カンマ区切り）</label>
              <input
                type="text"
                value={formData.tags}
                onChange={(e) => setFormData((prev) => ({ ...prev, tags: e.target.value }))}
                className="input-field"
                placeholder="収納, 時短, おしゃれ"
              />
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">メモ</label>
              <textarea
                value={formData.memo}
                onChange={(e) => setFormData((prev) => ({ ...prev, memo: e.target.value }))}
                className="input-field"
                rows={2}
                placeholder="商品の特徴や紹介ポイントなど"
              />
            </div>
          </div>

          {duplicateWarning && (
            <div className="flex items-center gap-2 text-amber-600 bg-amber-50 p-3 rounded-lg">
              <AlertTriangle className="w-4 h-4 flex-shrink-0" />
              <span className="text-sm">{duplicateWarning}</span>
            </div>
          )}

          {formError && (
            <div className="flex items-center gap-2 text-red-600 bg-red-50 p-3 rounded-lg">
              <AlertTriangle className="w-4 h-4 flex-shrink-0" />
              <span className="text-sm">{formError}</span>
            </div>
          )}

          <button type="submit" className="btn-primary flex items-center gap-2">
            <Plus className="w-4 h-4" />
            商品を追加
          </button>
        </form>
      </div>

      {/* CSVインポート */}
      <div className="card">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold">CSVインポート</h2>
          <div className="flex gap-2">
            <button
              onClick={handleCopySample}
              className="btn-secondary flex items-center gap-1 text-sm py-1"
            >
              <Copy className="w-3 h-3" />
              サンプルCSV
            </button>
            <button
              onClick={() => fileInputRef.current?.click()}
              className="btn-secondary flex items-center gap-1 text-sm py-1"
            >
              <Upload className="w-3 h-3" />
              ファイル選択
            </button>
          </div>
        </div>
        <input
          ref={fileInputRef}
          type="file"
          accept=".csv"
          onChange={handleFileUpload}
          className="hidden"
        />

        {showCsvInput && (
          <div className="space-y-3">
            <textarea
              value={csvText}
              onChange={(e) => setCsvText(e.target.value)}
              className="input-field font-mono text-sm"
              rows={6}
              placeholder="CSVデータを貼り付け..."
            />
            {csvErrors.length > 0 && (
              <div className="bg-red-50 p-3 rounded-lg">
                {csvErrors.map((error, i) => (
                  <p key={i} className="text-sm text-red-600">
                    {error}
                  </p>
                ))}
              </div>
            )}
            <div className="flex gap-2">
              <button onClick={handleCsvImport} className="btn-primary">
                インポート
              </button>
              <button onClick={() => setShowCsvInput(false)} className="btn-secondary">
                キャンセル
              </button>
            </div>
          </div>
        )}

        {!showCsvInput && (
          <button
            onClick={() => setShowCsvInput(true)}
            className="w-full border-2 border-dashed border-gray-300 rounded-lg p-6 text-gray-500 hover:border-gray-400 hover:text-gray-600 transition-colors"
          >
            <FileText className="w-8 h-8 mx-auto mb-2" />
            <p>CSVデータを貼り付けてインポート</p>
            <p className="text-xs mt-1">title, url, price, shop, memo, tags</p>
          </button>
        )}
      </div>

      {/* 商品一覧 */}
      {products.length > 0 && (
        <div className="card">
          <h2 className="text-lg font-bold mb-4">登録済み商品 ({products.length}件)</h2>
          <div className="space-y-3">
            {products.map((product) => (
              <div
                key={product.id}
                className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
              >
                <div className="flex-1 min-w-0 mr-4">
                  <h3 className="font-medium text-gray-900 truncate">{product.title}</h3>
                  <div className="flex items-center gap-2 text-sm text-gray-500">
                    <a
                      href={product.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center gap-1 hover:text-rakuten-red"
                    >
                      <ExternalLink className="w-3 h-3" />
                      URL
                    </a>
                    {product.price && <span>¥{product.price.toLocaleString()}</span>}
                    {product.shop && <span>{product.shop}</span>}
                  </div>
                  {product.tags.length > 0 && (
                    <div className="flex gap-1 mt-1 flex-wrap">
                      {product.tags.map((tag, i) => (
                        <span
                          key={i}
                          className="px-2 py-0.5 bg-gray-200 text-gray-600 text-xs rounded"
                        >
                          {tag}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleGeneratePost(product.id)}
                    className="btn-primary text-sm py-1"
                  >
                    生成
                  </button>
                  <button
                    onClick={() => deleteProduct(product.id)}
                    className="p-2 text-gray-400 hover:text-red-500 transition-colors"
                    title="削除"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
