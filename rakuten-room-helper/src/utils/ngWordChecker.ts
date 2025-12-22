import type { NgWarning } from '../types';

/**
 * NGワードのパターン定義
 */
interface NgPattern {
  pattern: RegExp;
  type: NgWarning['type'];
  message: string;
  severity: NgWarning['severity'];
}

/**
 * NGワードパターンのリスト
 * 誇大表現、医療効果、価格保証、他社批判などを検出
 */
const NG_PATTERNS: NgPattern[] = [
  // 誇大表現
  { pattern: /絶対に?(?:治|効き?|痩せ)/g, type: 'exaggeration', message: '断定的な効果表現は避けてください', severity: 'error' },
  { pattern: /必ず(?:効果|結果)/g, type: 'exaggeration', message: '効果の断定は避けてください', severity: 'error' },
  { pattern: /100%(?:効果|満足|治)/g, type: 'exaggeration', message: '効果の断定は避けてください', severity: 'error' },
  { pattern: /世界(?:一|初|最高)/g, type: 'exaggeration', message: '根拠のない最上級表現は避けてください', severity: 'warning' },
  { pattern: /業界(?:一|初|最高|No\.?1)/gi, type: 'exaggeration', message: '根拠のない業界No.1表現は避けてください', severity: 'warning' },
  { pattern: /(?:奇跡|魔法)の(?:効果|商品|アイテム)/g, type: 'exaggeration', message: '誇大な表現は避けてください', severity: 'warning' },
  { pattern: /驚異的な?効果/g, type: 'exaggeration', message: '誇大な効果表現は避けてください', severity: 'warning' },

  // 医療効果・薬機法関連
  { pattern: /(?:病気|疾患|症状)(?:が|を)?治/g, type: 'medical_claim', message: '医療効果の表現は薬機法違反の可能性があります', severity: 'error' },
  { pattern: /(?:ガン|癌|がん|糖尿病|高血圧)(?:に|を)?(?:効|治|予防)/g, type: 'medical_claim', message: '特定疾患への効果表現は薬機法違反です', severity: 'error' },
  { pattern: /医師(?:も)?(?:推奨|認め|驚)/g, type: 'medical_claim', message: '医師の推奨を謳う場合は根拠が必要です', severity: 'warning' },
  { pattern: /(?:アトピー|アレルギー)(?:に|が)?(?:効|治|改善)/g, type: 'medical_claim', message: '医療効果の表現は避けてください', severity: 'error' },
  { pattern: /(?:シミ|シワ|たるみ)(?:が)?(?:消え|なくな)/g, type: 'medical_claim', message: '効果の断定は避けてください', severity: 'warning' },
  { pattern: /(?:確実に|絶対に)?痩せ/g, type: 'medical_claim', message: 'ダイエット効果の断定は避けてください', severity: 'warning' },

  // 価格保証
  { pattern: /最安(?:値|価格)?(?:保証)?/g, type: 'price_guarantee', message: '最安値の断定は避けてください', severity: 'warning' },
  { pattern: /(?:どこよりも|他店より)安い/g, type: 'price_guarantee', message: '価格比較の断定は避けてください', severity: 'warning' },
  { pattern: /(?:業界|市場)最安/g, type: 'price_guarantee', message: '最安値の断定は避けてください', severity: 'warning' },
  { pattern: /激安|爆安|超安/g, type: 'price_guarantee', message: '過度な安さの強調は注意してください', severity: 'warning' },
  { pattern: /(?:今だけ|期間限定)(?:の)?(?:特別|特価|最安)/g, type: 'price_guarantee', message: '限定価格の表現は注意してください', severity: 'warning' },

  // 他社批判
  { pattern: /(?:他社|他店|競合)(?:より|と比べて)?(?:優れ|良い|上)/g, type: 'competitor_criticism', message: '他社との比較は注意してください', severity: 'warning' },
  { pattern: /(?:他社|他店|競合)(?:は|の)?(?:ダメ|悪い|劣|問題)/g, type: 'competitor_criticism', message: '他社批判は避けてください', severity: 'error' },
  { pattern: /(?:〇〇|△△|××)(?:より|と違って)(?:良い|優秀)/g, type: 'competitor_criticism', message: '特定他社との比較は避けてください', severity: 'warning' },

  // その他の注意表現
  { pattern: /(?:当店|弊社)(?:だけ|限定|オリジナル)/g, type: 'other', message: '独自性の主張には根拠を示してください', severity: 'warning' },
  { pattern: /(?:効果|効能)(?:が)?(?:すごい|抜群|最高)/g, type: 'other', message: '効果の誇大表現は避けてください', severity: 'warning' },
  { pattern: /(?:即効|即座に|すぐに)(?:効果|効|変わ)/g, type: 'other', message: '即効性の断定は避けてください', severity: 'warning' },
];

/**
 * テキスト内のNGワードをチェックする
 * @param text チェック対象のテキスト
 * @returns 検出されたNGワード警告の配列
 */
export function checkNgWords(text: string): NgWarning[] {
  const warnings: NgWarning[] = [];
  const seenWords = new Set<string>();

  for (const { pattern, type, message, severity } of NG_PATTERNS) {
    // パターンをリセット（グローバルフラグ対応）
    pattern.lastIndex = 0;

    let match;
    while ((match = pattern.exec(text)) !== null) {
      const word = match[0];
      // 重複を避ける
      if (!seenWords.has(word)) {
        seenWords.add(word);
        warnings.push({
          type,
          word,
          message,
          severity,
        });
      }
    }
  }

  return warnings;
}

/**
 * NGワードを含むかチェック（簡易版）
 * @param text チェック対象のテキスト
 * @returns NGワードを含むかどうか
 */
export function hasNgWords(text: string): boolean {
  return checkNgWords(text).length > 0;
}

/**
 * エラーレベルのNGワードを含むかチェック
 * @param text チェック対象のテキスト
 * @returns エラーレベルのNGワードを含むかどうか
 */
export function hasErrorNgWords(text: string): boolean {
  return checkNgWords(text).some((w) => w.severity === 'error');
}

/**
 * NGワードの種類ごとのラベル
 */
export const NG_TYPE_LABELS: Record<NgWarning['type'], string> = {
  exaggeration: '誇大表現',
  medical_claim: '医療効果',
  price_guarantee: '価格保証',
  competitor_criticism: '他社批判',
  other: 'その他',
};
