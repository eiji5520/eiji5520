<?php
/**
 * AAA_Provider_Claude クラス
 *
 * Claude API（Anthropic）を使用した記事生成
 * - wp_remote_post による API 呼び出し
 * - タイムアウト、リトライ、エラーハンドリング
 * - NGワードフィルタ
 * - 生成文字数制御
 * - 機微情報のログ出力禁止
 *
 * @package AI_Auto_Affiliate
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AAA_Provider_Claude
 */
class AAA_Provider_Claude {

    /**
     * Claude API エンドポイント
     *
     * @var string
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * API バージョン
     *
     * @var string
     */
    const API_VERSION = '2023-06-01';

    /**
     * タイムアウト秒数
     *
     * @var int
     */
    const TIMEOUT_SECONDS = 60;

    /**
     * 最大リトライ回数
     *
     * @var int
     */
    const MAX_RETRIES = 2;

    /**
     * 連打防止ロックの有効秒数
     *
     * @var int
     */
    const LOCK_DURATION = 10;

    /**
     * 設定値
     *
     * @var array
     */
    private $settings;

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->settings = aaa_get_settings();
    }

    /**
     * 記事を生成
     *
     * @param array $context 生成コンテキスト
     *   - keyword: キーワード/テーマ（必須）
     *   - a8_slot: A8スロット（任意）
     * @return array 結果
     *   成功: ['ok' => true, 'title' => '...', 'content' => '...', 'related' => [...]]
     *   失敗: ['ok' => false, 'error' => '...']
     */
    public function generate_article( array $context ) {
        $keyword = isset( $context['keyword'] ) ? $context['keyword'] : '';

        if ( empty( $keyword ) ) {
            return $this->error_response( 'キーワードが指定されていません。' );
        }

        // 連打防止チェック
        if ( $this->is_locked() ) {
            return $this->error_response( '短時間での連続リクエストは制限されています。10秒後に再試行してください。' );
        }

        // APIキーチェック
        $api_key = $this->settings['api_key'];
        if ( empty( $api_key ) ) {
            return $this->error_response( 'APIキーが設定されていません。' );
        }

        // ロックを設定
        $this->set_lock();

        // プロンプトを組み立て
        $prompt = $this->build_prompt( $keyword );

        // API呼び出し（リトライ付き）
        $response = $this->call_api_with_retry( $prompt );

        // ロックを解除
        $this->release_lock();

        if ( ! $response['ok'] ) {
            return $response;
        }

        // 生成結果を処理
        return $this->process_response( $response['raw_content'], $keyword );
    }

    /**
     * プロンプトを組み立て
     *
     * @param string $keyword キーワード
     * @return string プロンプト
     */
    private function build_prompt( $keyword ) {
        $template  = $this->settings['template'];
        $min_chars = $this->settings['min_chars'];
        $max_chars = $this->settings['max_chars'];
        $ng_words  = $this->get_ng_words_list();

        // NGワードリストを文字列化
        $ng_words_text = ! empty( $ng_words )
            ? '以下のNGワードは絶対に使用しないでください: ' . implode( '、', $ng_words )
            : '';

        $prompt = <<<PROMPT
あなたは日本語の記事ライターです。以下の指示に従って記事を作成してください。

## テーマ/キーワード
{$keyword}

## 記事の構成ルール
{$template}

## 必須ルール
1. 記事は日本語で書く
2. 口調は丁寧語（です/ます調）
3. 文字数は{$min_chars}〜{$max_chars}文字程度
4. 見出しはMarkdownの「##」を使用
5. 以下の表現は絶対に禁止：
   - 誇大表現（「絶対」「確実」「100%」「必ず」など）
   - 断定的な表現（「間違いなく」「絶対に」など）
   - 煽り表現（「今すぐ」「急いで」「限定」など）
   - 虚偽の情報
{$ng_words_text}

## 出力形式
記事本文をMarkdown形式で出力してください。

最後に「---」で区切って、以下の形式で関連記事候補を3つ提案してください：
---
RELATED:
1. [関連記事タイトル1]
2. [関連記事タイトル2]
3. [関連記事タイトル3]
PROMPT;

        return $prompt;
    }

    /**
     * NGワードリストを取得
     *
     * @return array NGワード配列
     */
    private function get_ng_words_list() {
        $ng_words_raw = $this->settings['ng_words'];
        if ( empty( $ng_words_raw ) ) {
            return array();
        }

        $lines = explode( "\n", $ng_words_raw );
        $words = array();

        foreach ( $lines as $line ) {
            $word = trim( $line );
            if ( ! empty( $word ) ) {
                $words[] = $word;
            }
        }

        return $words;
    }

    /**
     * APIをリトライ付きで呼び出し
     *
     * @param string $prompt プロンプト
     * @return array 結果
     */
    private function call_api_with_retry( $prompt ) {
        $retry_count = 0;
        $last_error  = '';

        while ( $retry_count <= self::MAX_RETRIES ) {
            // リトライ時は指数バックオフで待機
            if ( $retry_count > 0 ) {
                $wait_seconds = pow( 2, $retry_count ); // 2, 4秒
                sleep( $wait_seconds );

                $this->log_info( 'APIリトライ実行', array(
                    'retry'        => $retry_count,
                    'wait_seconds' => $wait_seconds,
                ) );
            }

            $result = $this->call_api( $prompt );

            // 成功
            if ( $result['ok'] ) {
                return $result;
            }

            // リトライ対象のエラーかチェック
            $http_code = isset( $result['http_code'] ) ? $result['http_code'] : 0;
            $is_retryable = ( $http_code === 429 || $http_code >= 500 );

            if ( ! $is_retryable ) {
                // リトライ不可のエラー
                return $result;
            }

            $last_error = $result['error'];
            $retry_count++;
        }

        // リトライ上限到達
        return $this->error_response( 'APIリクエストが失敗しました（リトライ上限）: ' . $last_error );
    }

    /**
     * Claude APIを呼び出し
     *
     * @param string $prompt プロンプト
     * @return array 結果
     */
    private function call_api( $prompt ) {
        $api_key = $this->settings['api_key'];
        $model   = $this->settings['model'];

        // リクエストボディ
        $body = array(
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        // リクエスト実行
        $start_time = microtime( true );

        $response = wp_remote_post(
            self::API_ENDPOINT,
            array(
                'timeout' => self::TIMEOUT_SECONDS,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $api_key,
                    'anthropic-version' => self::API_VERSION,
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        $elapsed_time = round( microtime( true ) - $start_time, 2 );

        // WP_Error チェック
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();

            // タイムアウト判定
            if ( strpos( $error_message, 'timed out' ) !== false ||
                 strpos( $error_message, 'timeout' ) !== false ) {
                $this->log_error( 'APIタイムアウト', array(
                    'elapsed_seconds' => $elapsed_time,
                ) );
                return $this->error_response( 'APIリクエストがタイムアウトしました。しばらく待ってから再試行してください。', 0 );
            }

            $this->log_error( 'API通信エラー', array(
                'error_type'      => 'wp_error',
                'elapsed_seconds' => $elapsed_time,
            ) );

            return $this->error_response( 'API通信エラー: ' . $error_message, 0 );
        }

        // HTTPステータスコード
        $http_code = wp_remote_retrieve_response_code( $response );
        $body_raw  = wp_remote_retrieve_body( $response );
        $body_json = json_decode( $body_raw, true );

        // エラーレスポンス
        if ( $http_code !== 200 ) {
            $error_message = isset( $body_json['error']['message'] )
                ? $body_json['error']['message']
                : 'Unknown error';

            // 特定エラーの日本語化
            $user_message = $this->translate_api_error( $http_code, $error_message );

            $this->log_error( 'APIエラーレスポンス', array(
                'http_code'       => $http_code,
                'elapsed_seconds' => $elapsed_time,
            ) );

            return $this->error_response( $user_message, $http_code );
        }

        // 成功レスポンスから本文を抽出
        if ( ! isset( $body_json['content'][0]['text'] ) ) {
            $this->log_error( 'APIレスポンス形式エラー', array(
                'http_code'       => $http_code,
                'elapsed_seconds' => $elapsed_time,
            ) );

            return $this->error_response( 'APIレスポンスの形式が不正です。' );
        }

        $generated_text = $body_json['content'][0]['text'];
        $char_count     = mb_strlen( $generated_text );

        $this->log_info( 'API呼び出し成功', array(
            'http_code'       => $http_code,
            'elapsed_seconds' => $elapsed_time,
            'char_count'      => $char_count,
        ) );

        return array(
            'ok'          => true,
            'raw_content' => $generated_text,
            'http_code'   => $http_code,
        );
    }

    /**
     * APIエラーメッセージを日本語化
     *
     * @param int    $http_code     HTTPステータスコード
     * @param string $error_message エラーメッセージ
     * @return string 日本語エラーメッセージ
     */
    private function translate_api_error( $http_code, $error_message ) {
        switch ( $http_code ) {
            case 401:
                return 'APIキーが無効です。設定ページで正しいキーを入力してください。';
            case 403:
                return 'APIへのアクセスが拒否されました。APIキーの権限を確認してください。';
            case 429:
                return 'APIレート制限に達しました。しばらく待ってから再試行してください。';
            case 500:
            case 502:
            case 503:
                return 'APIサーバーでエラーが発生しました。しばらく待ってから再試行してください。';
            default:
                return sprintf( 'APIエラー (HTTP %d): %s', $http_code, $error_message );
        }
    }

    /**
     * 生成レスポンスを処理
     *
     * @param string $raw_content 生成された本文
     * @param string $keyword     キーワード
     * @return array 処理結果
     */
    private function process_response( $raw_content, $keyword ) {
        // 関連記事を分離
        $parts         = $this->extract_related_articles( $raw_content );
        $content       = $parts['content'];
        $related       = $parts['related'];

        // NGワードチェック
        $ng_check = $this->check_ng_words( $content );
        if ( ! $ng_check['ok'] ) {
            $this->log_warning( 'NGワード検出', array(
                'keyword'   => mb_strimwidth( $keyword, 0, 30, '...' ),
                'ng_word'   => $ng_check['word'],
            ) );

            return $this->error_response(
                'NGワード「' . $ng_check['word'] . '」が含まれています。再生成してください。'
            );
        }

        // 文字数制御
        $content = $this->enforce_char_limit( $content );

        // タイトル生成（本文の最初の見出しまたはキーワードから）
        $title = $this->extract_or_generate_title( $content, $keyword );

        $char_count = mb_strlen( $content );

        $this->log_info( '記事生成完了', array(
            'keyword'    => mb_strimwidth( $keyword, 0, 30, '...' ),
            'char_count' => $char_count,
            'has_related' => ! empty( $related ),
        ) );

        return array(
            'ok'      => true,
            'title'   => $title,
            'content' => $content,
            'related' => $related,
        );
    }

    /**
     * 関連記事を本文から分離
     *
     * @param string $raw_content 生成本文
     * @return array ['content' => '...', 'related' => [...]]
     */
    private function extract_related_articles( $raw_content ) {
        $related = array();
        $content = $raw_content;

        // "---" + "RELATED:" パターンを検出
        if ( preg_match( '/---\s*\nRELATED:\s*\n(.+)$/s', $raw_content, $matches ) ) {
            // 関連記事部分を除去
            $content = preg_replace( '/---\s*\nRELATED:\s*\n.+$/s', '', $raw_content );
            $content = trim( $content );

            // 関連記事を抽出
            $related_text = $matches[1];
            if ( preg_match_all( '/\d+\.\s*\[?([^\]\n]+)\]?/', $related_text, $rel_matches ) ) {
                $related = $rel_matches[1];
            }
        }

        return array(
            'content' => $content,
            'related' => $related,
        );
    }

    /**
     * NGワードチェック
     *
     * @param string $content 本文
     * @return array ['ok' => bool, 'word' => string]
     */
    private function check_ng_words( $content ) {
        $ng_words = $this->get_ng_words_list();

        foreach ( $ng_words as $word ) {
            if ( mb_strpos( $content, $word ) !== false ) {
                return array(
                    'ok'   => false,
                    'word' => $word,
                );
            }
        }

        return array( 'ok' => true, 'word' => '' );
    }

    /**
     * 文字数制限を適用
     *
     * @param string $content 本文
     * @return string 制限後の本文
     */
    private function enforce_char_limit( $content ) {
        $max_chars = $this->settings['max_chars'];

        if ( mb_strlen( $content ) <= $max_chars ) {
            return $content;
        }

        // max_chars を超えた場合、段落区切りで切る
        $truncated = mb_substr( $content, 0, $max_chars );

        // 最後の段落区切り（改行2つ）を探す
        $last_para = mb_strrpos( $truncated, "\n\n" );
        if ( $last_para !== false && $last_para > $max_chars * 0.7 ) {
            $truncated = mb_substr( $truncated, 0, $last_para );
        }

        // まとめセクションを追加
        $truncated .= "\n\n## まとめ\n\nこの記事では上記のポイントについて解説しました。";

        $this->log_info( '文字数制限を適用', array(
            'original_chars'  => mb_strlen( $content ),
            'truncated_chars' => mb_strlen( $truncated ),
            'max_chars'       => $max_chars,
        ) );

        return $truncated;
    }

    /**
     * タイトルを抽出または生成
     *
     * @param string $content 本文
     * @param string $keyword キーワード
     * @return string タイトル
     */
    private function extract_or_generate_title( $content, $keyword ) {
        // 最初の見出し（##）を探す
        if ( preg_match( '/^##\s*(.+)$/m', $content, $matches ) ) {
            $title = trim( $matches[1] );
            // 「はじめに」「結論」などの汎用見出しでなければ使用
            if ( ! preg_match( '/^(はじめに|結論|まとめ|概要)$/u', $title ) ) {
                return $title;
            }
        }

        // キーワードからタイトル生成
        $first_line = strtok( $keyword, "\n" );
        $first_line = trim( $first_line );

        if ( mb_strlen( $first_line ) > 50 ) {
            $first_line = mb_substr( $first_line, 0, 50 );
        }

        return $first_line . 'について徹底解説';
    }

    /**
     * エラーレスポンスを生成
     *
     * @param string $message   エラーメッセージ
     * @param int    $http_code HTTPコード
     * @return array エラーレスポンス
     */
    private function error_response( $message, $http_code = 0 ) {
        return array(
            'ok'        => false,
            'error'     => $message,
            'http_code' => $http_code,
        );
    }

    /**
     * 連打防止ロック中かチェック
     *
     * @return bool ロック中かどうか
     */
    private function is_locked() {
        $user_id   = get_current_user_id();
        $lock_key  = 'aaa_generate_lock_' . $user_id;
        $lock_time = get_transient( $lock_key );

        return false !== $lock_time;
    }

    /**
     * 連打防止ロックを設定
     */
    private function set_lock() {
        $user_id  = get_current_user_id();
        $lock_key = 'aaa_generate_lock_' . $user_id;

        set_transient( $lock_key, time(), self::LOCK_DURATION );
    }

    /**
     * 連打防止ロックを解除
     */
    private function release_lock() {
        $user_id  = get_current_user_id();
        $lock_key = 'aaa_generate_lock_' . $user_id;

        delete_transient( $lock_key );
    }

    /**
     * INFOログを記録
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト
     */
    private function log_info( $message, $context = array() ) {
        if ( class_exists( 'AAA_Logger' ) ) {
            AAA_Logger::get_instance()->info( '[Claude] ' . $message, $context );
        }
    }

    /**
     * WARNINGログを記録
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト
     */
    private function log_warning( $message, $context = array() ) {
        if ( class_exists( 'AAA_Logger' ) ) {
            AAA_Logger::get_instance()->warning( '[Claude] ' . $message, $context );
        }
    }

    /**
     * ERRORログを記録
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト
     */
    private function log_error( $message, $context = array() ) {
        if ( class_exists( 'AAA_Logger' ) ) {
            AAA_Logger::get_instance()->error( '[Claude] ' . $message, $context );
        }
    }
}
