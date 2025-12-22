import type { TemplateType, TemplateConfig } from '../types';

/**
 * 投稿文テンプレートの設定
 */
export const TEMPLATE_CONFIGS: TemplateConfig[] = [
  {
    type: 'simple',
    label: 'シンプル',
    description: 'ベーシックな紹介文。汎用性が高い',
    prompt: `商品を紹介する投稿文を生成してください。

【出力ルール】
- 日本語で100〜180文字程度
- 絵文字は0〜2個まで（控えめに）
- 商品の特徴や魅力を1つ
- 利用シーンを1つ
- ハッシュタグ2〜5個（#楽天ROOM は必須）
- URLは末尾に配置

【禁止事項】
- 効果の断定（「必ず」「絶対」「100%」など）
- 価格の断定（「最安」など）
- 医療効果の表現
- 他社批判

【出力形式】
本文（URL含む）のみを出力してください。`,
  },
  {
    type: 'comparison',
    label: '比較・検討',
    description: '類似商品と比べた特徴を強調',
    prompt: `商品を比較検討の視点から紹介する投稿文を生成してください。

【出力ルール】
- 日本語で100〜180文字程度
- 絵文字は0〜2個まで（控えめに）
- 「〜を探している方に」「〜で迷っている方に」などの導入
- この商品ならではの特徴を1つ強調
- ハッシュタグ2〜5個（#楽天ROOM は必須）
- URLは末尾に配置

【禁止事項】
- 具体的な他社製品名・ブランド名との比較
- 効果の断定
- 価格の断定（「最安」「一番安い」など）

【出力形式】
本文（URL含む）のみを出力してください。`,
  },
  {
    type: 'time_saving',
    label: '時短訴求',
    description: '忙しい人向けの時短・効率アピール',
    prompt: `忙しい人や時短を求める人向けに商品を紹介する投稿文を生成してください。

【出力ルール】
- 日本語で100〜180文字程度
- 絵文字は0〜2個まで（控えめに）
- 「忙しい毎日に」「時短になる」「手軽に」などの導入
- 時間効率や手軽さの観点からメリットを1つ
- ハッシュタグ2〜5個（#楽天ROOM #時短 などを含む）
- URLは末尾に配置

【禁止事項】
- 効果の断定
- 誇張表現

【出力形式】
本文（URL含む）のみを出力してください。`,
  },
  {
    type: 'gift',
    label: 'ギフト',
    description: 'プレゼント・贈り物向け',
    prompt: `プレゼントやギフトとして商品を紹介する投稿文を生成してください。

【出力ルール】
- 日本語で100〜180文字程度
- 絵文字は0〜2個まで（控えめに）
- 「贈り物に」「プレゼントに」などの導入
- 誰に向けた贈り物として良いか（例：友人、家族、自分へのご褒美）
- ハッシュタグ2〜5個（#楽天ROOM #プレゼント などを含む）
- URLは末尾に配置

【禁止事項】
- 効果の断定
- 価格の断定

【出力形式】
本文（URL含む）のみを出力してください。`,
  },
  {
    type: 'cost_performance',
    label: 'コスパ',
    description: 'お得感・コストパフォーマンス重視',
    prompt: `コストパフォーマンスの観点から商品を紹介する投稿文を生成してください。

【出力ルール】
- 日本語で100〜180文字程度
- 絵文字は0〜2個まで（控えめに）
- 「この価格で」「お得感」などの導入
- 価格に対して得られる価値や品質を強調
- ハッシュタグ2〜5個（#楽天ROOM #コスパ などを含む）
- URLは末尾に配置

【禁止事項】
- 「最安」「一番安い」などの価格断定
- 具体的な他社製品との価格比較
- 効果の断定

【出力形式】
本文（URL含む）のみを出力してください。`,
  },
];

/**
 * テンプレートタイプからラベルを取得
 */
export function getTemplateLabel(type: TemplateType): string {
  const config = TEMPLATE_CONFIGS.find((c) => c.type === type);
  return config?.label || type;
}

/**
 * テンプレートタイプからプロンプトを取得
 */
export function getTemplatePrompt(type: TemplateType): string {
  const config = TEMPLATE_CONFIGS.find((c) => c.type === type);
  return config?.prompt || TEMPLATE_CONFIGS[0].prompt;
}

/**
 * 全テンプレートタイプのリスト
 */
export const ALL_TEMPLATE_TYPES: TemplateType[] = TEMPLATE_CONFIGS.map((c) => c.type);
