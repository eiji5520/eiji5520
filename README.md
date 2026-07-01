# EDINET非上場企業 配当情報収集ツール

EDINET API v2を使い、有価証券報告書の提出義務がある**非上場企業**の配当情報を自動収集し、CSV/Excelとして出力するPythonツールです。

## 前提条件

- Python 3.11以上
- EDINET API v2のサブスクリプションキー

## セットアップ

### 1. EDINET APIキーの取得

1. [EDINET API](https://disclosure.edinet-fsa.go.jp/) にアクセス
2. 「EDINET APIについて」からAPI利用の申請を行う
3. 発行されたサブスクリプションキーを環境変数に設定

```bash
export EDINET_API_KEY="your-subscription-key"
```

### 2. 依存パッケージのインストール

```bash
pip install -r requirements.txt
```

## 使い方

### 基本実行（直近1年分）

```bash
python main.py
```

### 期間を指定して実行

```bash
python main.py --start-date 2024-01-01 --end-date 2024-06-30
```

### 出力ファイル名を指定

```bash
python main.py --output-csv result.csv --output-excel result.xlsx
```

### 上場企業も含める

```bash
python main.py --include-listed
```

### デバッグログを出力

```bash
python main.py -v
```

## 出力項目

| 列名 | 説明 |
|------|------|
| 会社名 | 提出者名 |
| EDINETコード | EDINET上の企業コード |
| 業種 | XBRLの業種コード |
| 資本金 | 資本金（円） |
| 従業員数 | 従業員数（人） |
| 決算期 | 事業年度末日 |
| 1株配当 | 1株当たり年間配当額（円） |
| 配当性向 | 配当性向（%、記載がある場合） |
| 配当金総額 | 配当金総額（円、記載がある場合） |
| エビデンスURL | EDINET書類閲覧URL |
| docID | EDINET書類ID |

## テスト

サントリーホールディングス等の既知企業を対象にした動作確認テスト：

```bash
EDINET_API_KEY=your-key python test_known_companies.py
```

## ファイル構成

```
main.py                  - メイン実行スクリプト
edinet_client.py         - EDINET API呼び出しラッパー
xbrl_parser.py           - XBRL解析ロジック
test_known_companies.py  - 動作確認テスト
requirements.txt         - 依存パッケージ一覧
```

## 注意事項

- EDINET APIの利用規約を遵守してください
- リクエスト間に1秒のインターバルを設けています（変更する場合は`edinet_client.py`の`REQUEST_INTERVAL`を調整）
- 大量取得時は日付範囲を適切に区切って実行してください
- ネットワークエラー時は最大3回リトライします（指数バックオフ）
- 配当情報のXBRLタグは年度によって異なる場合があり、一部取得できないことがあります
