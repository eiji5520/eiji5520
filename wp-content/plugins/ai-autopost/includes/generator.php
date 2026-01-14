<?php
/**
 * 記事生成機能
 *
 * @package AI_AutoPost
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 記事生成クラス
 */
class AI_AutoPost_Generator {

    /**
     * OpenAI API エンドポイント
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * APIタイムアウト（秒）
     */
    const API_TIMEOUT = 120;

    /**
     * キューオプション名
     */
    const QUEUE_OPTION = 'ai_autopost_queue';

    /**
     * 記事を生成して投稿
     *
     * @param string $keyword     キーワード
     * @param string $post_status 投稿ステータス（draft/publish）
     * @return array 結果配列（success, message, post_id）
     */
    public static function generate_and_post( $keyword, $post_status = null ) {
        // 設定を取得
        $settings = AI_AutoPost_Settings::get_all();

        // APIキーチェック
        if ( empty( $settings['api_key'] ) ) {
            $error = 'APIキーが設定されていません';
            AI_AutoPost_Logger::error( $error, array( 'keyword' => $keyword ) );
            return array(
                'success' => false,
                'message' => $error,
                'post_id' => 0,
            );
        }

        // 投稿ステータス（引数優先、なければ設定値）
        if ( null === $post_status ) {
            $post_status = $settings['post_status'];
        }

        // 重複チェック
        $duplicate_check = self::check_duplicate( $keyword );
        if ( $duplicate_check['exists'] ) {
            $message = sprintf(
                '重複スキップ：キーワード「%s」は既に投稿済みです（投稿ID: %d）',
                $keyword,
                $duplicate_check['post_id']
            );
            AI_AutoPost_Logger::skip( $message, array(
                'keyword' => $keyword,
                'post_id' => $duplicate_check['post_id'],
            ) );
            return array(
                'success' => false,
                'message' => $message,
                'post_id' => 0,
                'skipped' => true,
            );
        }

        // OpenAI APIで記事を生成
        $result = self::call_openai_api( $keyword, $settings );

        if ( ! $result['success'] ) {
            AI_AutoPost_Logger::error( $result['message'], array(
                'keyword'   => $keyword,
                'http_code' => isset( $result['http_code'] ) ? $result['http_code'] : '',
                'response'  => isset( $result['response'] ) ? $result['response'] : '',
            ) );
            return $result;
        }

        // 記事内容を取得
        $content = $result['content'];
        $title   = self::extract_title( $content, $keyword );

        // タイトル重複チェック
        $title_check = self::check_title_duplicate( $title );
        if ( $title_check['exists'] ) {
            $message = sprintf(
                '重複スキップ：タイトル「%s」は既に存在します（投稿ID: %d）',
                $title,
                $title_check['post_id']
            );
            AI_AutoPost_Logger::skip( $message, array(
                'keyword' => $keyword,
                'title'   => $title,
                'post_id' => $title_check['post_id'],
            ) );
            return array(
                'success' => false,
                'message' => $message,
                'post_id' => 0,
                'skipped' => true,
            );
        }

        // WordPress投稿を作成
        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $post_status,
            'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
            'post_type'    => 'post',
        );

        // カテゴリを設定
        if ( ! empty( $settings['category_id'] ) && $settings['category_id'] > 0 ) {
            $post_data['post_category'] = array( intval( $settings['category_id'] ) );
        }

        // 投稿を挿入
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            $error = sprintf( '投稿作成エラー：%s', $post_id->get_error_message() );
            AI_AutoPost_Logger::error( $error, array(
                'keyword' => $keyword,
                'title'   => $title,
            ) );
            return array(
                'success' => false,
                'message' => $error,
                'post_id' => 0,
            );
        }

        // メタデータを保存（生成に使用したキーワード）
        update_post_meta( $post_id, '_ai_autopost_keyword', $keyword );
        update_post_meta( $post_id, '_ai_autopost_generated', current_time( 'mysql' ) );

        // 成功ログ
        $status_label = 'publish' === $post_status ? '公開' : '下書き';
        $message      = sprintf(
            '記事生成成功：「%s」を%sとして投稿しました（投稿ID: %d）',
            $title,
            $status_label,
            $post_id
        );
        AI_AutoPost_Logger::success( $message, array(
            'keyword' => $keyword,
            'title'   => $title,
            'post_id' => $post_id,
            'status'  => $post_status,
        ) );

        return array(
            'success' => true,
            'message' => $message,
            'post_id' => $post_id,
        );
    }

    /**
     * OpenAI APIを呼び出し
     *
     * @param string $keyword  キーワード
     * @param array  $settings 設定
     * @return array 結果配列
     */
    private static function call_openai_api( $keyword, $settings ) {
        // システムプロンプト
        $system_prompt = self::get_system_prompt();

        // ユーザープロンプト
        $user_prompt = self::get_user_prompt( $keyword, $settings );

        // リクエストボディ
        $body = array(
            'model'       => $settings['model'],
            'temperature' => floatval( $settings['temperature'] ),
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => $system_prompt,
                ),
                array(
                    'role'    => 'user',
                    'content' => $user_prompt,
                ),
            ),
        );

        // リクエストを送信
        $response = wp_remote_post(
            self::API_ENDPOINT,
            array(
                'timeout' => self::API_TIMEOUT,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $settings['api_key'],
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        // エラーチェック
        if ( is_wp_error( $response ) ) {
            return array(
                'success'  => false,
                'message'  => sprintf( 'HTTP通信エラー：%s', $response->get_error_message() ),
                'response' => '',
            );
        }

        $http_code     = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // HTTPステータスコードチェック
        if ( 200 !== $http_code ) {
            $error_message = self::parse_api_error( $response_body, $http_code );
            return array(
                'success'   => false,
                'message'   => $error_message,
                'http_code' => $http_code,
                'response'  => $response_body,
            );
        }

        // レスポンスをパース
        $data = json_decode( $response_body, true );

        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return array(
                'success'   => false,
                'message'   => 'APIレスポンスが空です',
                'http_code' => $http_code,
                'response'  => $response_body,
            );
        }

        $content = $data['choices'][0]['message']['content'];

        // コンテンツの検証
        if ( mb_strlen( $content ) < 100 ) {
            return array(
                'success'   => false,
                'message'   => '生成されたコンテンツが短すぎます',
                'http_code' => $http_code,
                'response'  => $response_body,
            );
        }

        return array(
            'success' => true,
            'content' => $content,
        );
    }

    /**
     * APIエラーをパース
     *
     * @param string $response_body レスポンスボディ
     * @param int    $http_code     HTTPステータスコード
     * @return string
     */
    private static function parse_api_error( $response_body, $http_code ) {
        $data = json_decode( $response_body, true );

        $error_message = '';
        if ( isset( $data['error']['message'] ) ) {
            $error_message = $data['error']['message'];
        }

        switch ( $http_code ) {
            case 401:
                return sprintf( 'APIキーが無効です（401）：%s', $error_message );
            case 403:
                return sprintf( 'アクセス拒否（403）：%s', $error_message );
            case 429:
                return sprintf( 'レート制限超過（429）：%s', $error_message );
            case 500:
            case 502:
            case 503:
                return sprintf( 'OpenAIサーバーエラー（%d）：%s', $http_code, $error_message );
            default:
                return sprintf( 'APIエラー（%d）：%s', $http_code, $error_message );
        }
    }

    /**
     * システムプロンプトを取得
     *
     * @return string
     */
    private static function get_system_prompt() {
        return <<<PROMPT
あなたは日本語のトラブル解決ブログ記事を執筆する編集者です。

【基本ルール】
- 記事は800〜1200文字程度で作成してください
- 見出しは ## や ### を使用してください
- 「〜です」「〜ます」の丁寧語で統一してください
- 断定的な表現（「必ず」「絶対に」など）は避けてください
- 誇大表現や煽り表現は使用しないでください
- 医療・金融に関する断定的なアドバイスは避けてください
- 広告クリックを誘導する表現は使用しないでください
- 読者が実際に問題を解決できる、実用的な内容にしてください

【文体】
- 初心者にもわかりやすい言葉を使用してください
- 専門用語は使う場合は簡単な説明を添えてください
- 箇条書きや番号付きリストを効果的に使用してください
PROMPT;
    }

    /**
     * ユーザープロンプトを取得
     *
     * @param string $keyword  キーワード
     * @param array  $settings 設定
     * @return string
     */
    private static function get_user_prompt( $keyword, $settings ) {
        $device    = isset( $settings['device'] ) ? $settings['device'] : 'Android/iPhone';
        $situation = isset( $settings['situation'] ) ? $settings['situation'] : 'アプリや端末で問題が起きて困っている';

        return <<<PROMPT
【サイト】life-helpnote.com
【キーワード】{$keyword}
【想定読者】{$device}の初心者ユーザー
【状況】{$situation}

以下の見出し構成で記事を作成してください：

1) ## まず最初にやること
   - 冒頭で「まず最初にやること（3つ）」を箇条書きで提示してください

2) ## よくある原因
   - 問題が起こる主な原因を3〜5個挙げてください

3) ## 今すぐできる対処法
   - 具体的な対処法を番号付きで5〜8個記載してください
   - 設定手順は「設定→〇〇→△△」のように道順を明記してください

4) ## それでも直らない場合
   - 上記で解決しない場合の追加対処法を記載してください

5) ## よくある質問（FAQ）
   - 関連するFAQを3つ、Q&A形式で記載してください

6) ## まとめ
   - 記事の要点を簡潔にまとめてください
PROMPT;
    }

    /**
     * コンテンツからタイトルを抽出
     *
     * @param string $content コンテンツ
     * @param string $keyword キーワード
     * @return string
     */
    private static function extract_title( $content, $keyword ) {
        // 最初の見出しをタイトルとして使用を試みる
        if ( preg_match( '/^#\s+(.+)$/m', $content, $matches ) ) {
            return trim( $matches[1] );
        }

        // 最初の行をタイトルとして使用を試みる
        $lines = explode( "\n", $content );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( ! empty( $line ) && mb_strlen( $line ) < 100 ) {
                // マークダウン記号を除去
                $line = preg_replace( '/^#+\s*/', '', $line );
                if ( ! empty( $line ) ) {
                    return $line;
                }
            }
        }

        // フォールバック：キーワードからタイトルを生成
        return sprintf( '【解決】%sのトラブル対処法', $keyword );
    }

    /**
     * キーワードの重複をチェック
     *
     * @param string $keyword キーワード
     * @return array
     */
    private static function check_duplicate( $keyword ) {
        global $wpdb;

        // メタデータから検索
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_ai_autopost_keyword'
                AND pm.meta_value = %s
                AND p.post_status IN ('publish', 'draft', 'pending')
                LIMIT 1",
                $keyword
            )
        );

        if ( $post_id ) {
            return array(
                'exists'  => true,
                'post_id' => intval( $post_id ),
            );
        }

        return array(
            'exists'  => false,
            'post_id' => 0,
        );
    }

    /**
     * タイトルの重複をチェック
     *
     * @param string $title タイトル
     * @return array
     */
    private static function check_title_duplicate( $title ) {
        global $wpdb;

        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_title = %s
                AND post_status IN ('publish', 'draft', 'pending')
                AND post_type = 'post'
                LIMIT 1",
                $title
            )
        );

        if ( $post_id ) {
            return array(
                'exists'  => true,
                'post_id' => intval( $post_id ),
            );
        }

        return array(
            'exists'  => false,
            'post_id' => 0,
        );
    }

    /**
     * キューにキーワードを追加
     *
     * @param array $keywords キーワード配列
     * @return int 追加された件数
     */
    public static function add_to_queue( $keywords ) {
        if ( ! is_array( $keywords ) ) {
            $keywords = array( $keywords );
        }

        $queue = get_option( self::QUEUE_OPTION, array() );
        $added = 0;

        foreach ( $keywords as $keyword ) {
            $keyword = sanitize_text_field( trim( $keyword ) );
            if ( ! empty( $keyword ) && ! in_array( $keyword, $queue, true ) ) {
                $queue[] = $keyword;
                $added++;
            }
        }

        update_option( self::QUEUE_OPTION, $queue );

        if ( $added > 0 ) {
            AI_AutoPost_Logger::info(
                sprintf( 'キューに%d件のキーワードを追加しました', $added ),
                array( 'count' => $added )
            );
        }

        return $added;
    }

    /**
     * キューからキーワードを取得（先頭）
     *
     * @return string|null
     */
    public static function get_from_queue() {
        $queue = get_option( self::QUEUE_OPTION, array() );

        if ( empty( $queue ) ) {
            return null;
        }

        return $queue[0];
    }

    /**
     * キューから先頭のキーワードを削除
     *
     * @return bool
     */
    public static function remove_from_queue() {
        $queue = get_option( self::QUEUE_OPTION, array() );

        if ( empty( $queue ) ) {
            return false;
        }

        array_shift( $queue );
        update_option( self::QUEUE_OPTION, $queue );

        return true;
    }

    /**
     * キューの末尾にキーワードを移動（リトライ用）
     *
     * @param string $keyword キーワード
     */
    public static function move_to_queue_end( $keyword ) {
        $queue = get_option( self::QUEUE_OPTION, array() );

        // 先頭から削除
        if ( ! empty( $queue ) && $queue[0] === $keyword ) {
            array_shift( $queue );
        }

        // 末尾に追加
        $queue[] = $keyword;

        update_option( self::QUEUE_OPTION, $queue );
    }

    /**
     * キューを取得
     *
     * @param int $limit 取得件数
     * @return array
     */
    public static function get_queue( $limit = 20 ) {
        $queue = get_option( self::QUEUE_OPTION, array() );
        return array_slice( $queue, 0, $limit );
    }

    /**
     * キューの件数を取得
     *
     * @return int
     */
    public static function get_queue_count() {
        $queue = get_option( self::QUEUE_OPTION, array() );
        return count( $queue );
    }

    /**
     * キューをクリア
     */
    public static function clear_queue() {
        update_option( self::QUEUE_OPTION, array() );
        AI_AutoPost_Logger::info( 'キューをクリアしました' );
    }

    /**
     * キューから特定のキーワードを削除
     *
     * @param string $keyword キーワード
     * @return bool
     */
    public static function remove_keyword_from_queue( $keyword ) {
        $queue = get_option( self::QUEUE_OPTION, array() );
        $key   = array_search( $keyword, $queue, true );

        if ( false !== $key ) {
            unset( $queue[ $key ] );
            $queue = array_values( $queue ); // インデックスを振り直し
            update_option( self::QUEUE_OPTION, $queue );
            return true;
        }

        return false;
    }
}
