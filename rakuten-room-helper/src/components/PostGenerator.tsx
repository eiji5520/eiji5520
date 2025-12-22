import { useState } from 'react';
import { useStore } from '../stores/useStore';
import { generatePostContent } from '../lib/claude';
import { TEMPLATE_CONFIGS, getTemplateLabel } from '../lib/templates';
import { NG_TYPE_LABELS } from '../utils/ngWordChecker';
import type { TemplateType, GeneratedContent } from '../types';
import {
  Wand2,
  Copy,
  Check,
  AlertTriangle,
  AlertCircle,
  Save,
  Loader2,
  ChevronDown,
} from 'lucide-react';

export default function PostGenerator() {
  const {
    products,
    selectedProductId,
    selectProduct,
    settings,
    generatedContents,
    setGeneratedContents,
    isGenerating,
    setIsGenerating,
    addPost,
    setActiveTab,
  } = useStore();

  const [selectedTemplate, setSelectedTemplate] = useState<TemplateType>(
    settings.defaultTemplate
  );
  const [error, setError] = useState<string | null>(null);
  const [copiedId, setCopiedId] = useState<string | null>(null);

  const selectedProduct = products.find((p) => p.id === selectedProductId);

  // 投稿文生成
  const handleGenerate = async () => {
    if (!selectedProduct) {
      setError('商品を選択してください');
      return;
    }
    if (!settings.apiKey) {
      setError('設定画面でAPIキーを設定してください');
      return;
    }

    setError(null);
    setIsGenerating(true);

    try {
      const contents = await generatePostContent(
        settings.apiKey,
        selectedProduct,
        selectedTemplate,
        settings.maxGenerations
      );
      setGeneratedContents(contents);
    } catch (err) {
      setError(err instanceof Error ? err.message : '生成に失敗しました');
    } finally {
      setIsGenerating(false);
    }
  };

  // クリップボードにコピー
  const handleCopy = async (content: GeneratedContent) => {
    try {
      await navigator.clipboard.writeText(content.content);
      setCopiedId(content.id);
      setTimeout(() => setCopiedId(null), 2000);
    } catch {
      setError('コピーに失敗しました');
    }
  };

  // 投稿として保存
  const handleSave = (content: GeneratedContent) => {
    if (!selectedProduct) return;
    addPost(selectedProduct.id, content.content, content.templateType);
    setActiveTab('manage');
  };

  return (
    <div className="space-y-6">
      {/* 商品選択 */}
      <div className="card">
        <h2 className="text-lg font-bold mb-4">商品を選択</h2>
        {products.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            <p>商品が登録されていません</p>
            <button
              onClick={() => setActiveTab('input')}
              className="mt-2 text-rakuten-red hover:underline"
            >
              商品を追加する
            </button>
          </div>
        ) : (
          <div className="relative">
            <select
              value={selectedProductId || ''}
              onChange={(e) => selectProduct(e.target.value || null)}
              className="input-field appearance-none pr-10"
            >
              <option value="">商品を選択...</option>
              {products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.title}
                  {product.price ? ` (¥${product.price.toLocaleString()})` : ''}
                </option>
              ))}
            </select>
            <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" />
          </div>
        )}

        {selectedProduct && (
          <div className="mt-4 p-3 bg-gray-50 rounded-lg">
            <h3 className="font-medium">{selectedProduct.title}</h3>
            <p className="text-sm text-gray-500 truncate">{selectedProduct.url}</p>
            {selectedProduct.memo && (
              <p className="text-sm text-gray-600 mt-1">{selectedProduct.memo}</p>
            )}
          </div>
        )}
      </div>

      {/* テンプレート選択 */}
      <div className="card">
        <h2 className="text-lg font-bold mb-4">テンプレートを選択</h2>
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          {TEMPLATE_CONFIGS.map((template) => (
            <button
              key={template.type}
              onClick={() => setSelectedTemplate(template.type)}
              className={`p-3 rounded-lg border-2 transition-all text-left ${
                selectedTemplate === template.type
                  ? 'border-rakuten-red bg-red-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <div className="font-medium text-sm">{template.label}</div>
              <div className="text-xs text-gray-500 mt-1">{template.description}</div>
            </button>
          ))}
        </div>
      </div>

      {/* 生成ボタン */}
      <div className="flex flex-col items-center gap-4">
        <button
          onClick={handleGenerate}
          disabled={!selectedProduct || isGenerating || !settings.apiKey}
          className="btn-primary flex items-center gap-2 px-8 py-3 text-lg disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isGenerating ? (
            <>
              <Loader2 className="w-5 h-5 animate-spin" />
              生成中...
            </>
          ) : (
            <>
              <Wand2 className="w-5 h-5" />
              投稿文を生成
            </>
          )}
        </button>

        {!settings.apiKey && (
          <p className="text-sm text-amber-600">
            <AlertTriangle className="w-4 h-4 inline mr-1" />
            設定画面でClaude APIキーを設定してください
          </p>
        )}

        {error && (
          <div className="flex items-center gap-2 text-red-600 bg-red-50 p-3 rounded-lg">
            <AlertCircle className="w-4 h-4 flex-shrink-0" />
            <span className="text-sm">{error}</span>
          </div>
        )}
      </div>

      {/* 生成結果 */}
      {generatedContents.length > 0 && (
        <div className="card">
          <h2 className="text-lg font-bold mb-4">
            生成結果 ({generatedContents.length}案)
          </h2>
          <div className="space-y-4">
            {generatedContents.map((content, index) => (
              <div key={content.id} className="border rounded-lg overflow-hidden">
                <div className="flex items-center justify-between bg-gray-50 px-4 py-2 border-b">
                  <div className="flex items-center gap-2">
                    <span className="font-medium">案{index + 1}</span>
                    <span className="text-sm text-gray-500">
                      ({getTemplateLabel(content.templateType)})
                    </span>
                  </div>
                  <div className="flex gap-2">
                    <button
                      onClick={() => handleCopy(content)}
                      className="btn-secondary flex items-center gap-1 text-sm py-1"
                    >
                      {copiedId === content.id ? (
                        <>
                          <Check className="w-3 h-3" />
                          コピー済み
                        </>
                      ) : (
                        <>
                          <Copy className="w-3 h-3" />
                          コピー
                        </>
                      )}
                    </button>
                    <button
                      onClick={() => handleSave(content)}
                      className="btn-success flex items-center gap-1 text-sm py-1"
                    >
                      <Save className="w-3 h-3" />
                      保存
                    </button>
                  </div>
                </div>
                <div className="p-4">
                  <p className="whitespace-pre-wrap text-gray-800">{content.content}</p>
                  <p className="text-xs text-gray-400 mt-2">
                    {content.content.length}文字
                  </p>
                </div>
                {content.ngWarnings.length > 0 && (
                  <div className="px-4 py-3 bg-amber-50 border-t border-amber-100">
                    <div className="flex items-start gap-2">
                      <AlertTriangle className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                      <div>
                        <p className="text-sm font-medium text-amber-800">
                          注意が必要な表現があります
                        </p>
                        <ul className="mt-1 space-y-1">
                          {content.ngWarnings.map((warning, i) => (
                            <li key={i} className="text-sm text-amber-700">
                              <span className="font-medium">
                                [{NG_TYPE_LABELS[warning.type]}]
                              </span>{' '}
                              「{warning.word}」- {warning.message}
                            </li>
                          ))}
                        </ul>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
