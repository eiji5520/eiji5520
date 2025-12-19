<?php
/**
 * AAA_Generator クラス
 *
 * 記事生成ページの表示とPOST処理
 * - フォーム表示（キーワード、カテゴリ、タグ、A8スロット選択）
 * - nonce検証、capability チェック
 * - PRGパターンで二重送信防止
 * - 投稿ステータスは必ず 'draft'（公開禁止）
 *
 * @package AI_Auto_Affiliate
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AAA_Generator
 */
class AAA_Generator {

    /**
     * Nonce アクション名
     *
     * @var string
     */
    const NONCE_ACTION = 'aaa_generate_article_action';

    /**
     * Nonce フィールド名
     *
     * @var string
     */
    const NONCE_NAME = 'aaa_generate_nonce';

    /**
     * トランジェントキー（結果メッセージ用）
     *
     * @var string
     */
    const TRANSIENT_KEY = 'aaa_generate_result_';

    /**
     * コンストラクタ
     */
    public function __construct() {
        // admin-post.php ハンドラを登録
        add_action( 'admin_post_aaa_generate_article', array( $this, 'handle_generate_post' ) );

        // メニューページのコールバックを上書き
        add_action( 'admin_menu', array( $this, 'override_menu_callback' ), 20 );
    }

    /**
     * メニューコールバックを上書き
     */
    public function override_menu_callback() {
        global $submenu;

        // AAA_Settings で登録されたメニューを上書き
        // メインメニューの表示コールバックをこのクラスに変更
        remove_submenu_page( 'ai-auto-affiliate', 'ai-auto-affiliate' );

        add_submenu_page(
            'ai-auto-affiliate',
            '記事生成',
            '記事生成',
            AAA_CAPABILITY,
            'ai-auto-affiliate',
            array( $this, 'render_generator_page' )
        );
    }

    /**
     * 生成ページのレンダリング
     */
    public function render_generator_page() {
        // 権限チェック
        if ( ! current_user_can( AAA_CAPABILITY ) ) {
            wp_die( 'この操作を実行する権限がありません。' );
        }

        // 結果メッセージを取得（トランジェントから）
        $user_id = get_current_user_id();
        $result  = get_transient( self::TRANSIENT_KEY . $user_id );

        if ( false !== $result ) {
            // 取得後は削除
            delete_transient( self::TRANSIENT_KEY . $user_id );
        }

        // 設定を取得
        $settings = aaa_get_settings();
        $a8_slots = isset( $settings['a8_slots'] ) ? $settings['a8_slots'] : array();

        // カテゴリ一覧を取得
        $categories = get_categories( array(
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        ?>
        <div class="wrap aaa-wrap">
            <h1>AI 記事生成</h1>

            <?php $this->render_result_message( $result ); ?>

            <?php $this->render_api_status_notice( $settings ); ?>

            <div class="aaa-card">
                <h2>記事を生成する</h2>
                <p>キーワード/テーマを入力して「下書き生成」ボタンをクリックしてください。<br>
                生成された記事は<strong>下書き</strong>として保存されます（自動公開はされません）。</p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aaa-generator-form">
                    <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
                    <input type="hidden" name="action" value="aaa_generate_article" />

                    <!-- キーワード/テーマ（必須） -->
                    <div class="form-row">
                        <label for="aaa_keyword">キーワード / テーマ <span style="color: #d63638;">*必須</span></label>
                        <textarea
                            name="aaa_keyword"
                            id="aaa_keyword"
                            rows="3"
                            placeholder="例: 肩こり 解消法&#10;例: 初心者向けプログラミング学習&#10;例: 一人暮らし 節約術"
                            required
                        ></textarea>
                        <p class="aaa-description">記事のテーマとなるキーワードを入力してください。複数のキーワードや文章でも可。</p>
                    </div>

                    <div class="aaa-inline-fields">
                        <!-- カテゴリ選択（任意） -->
                        <div class="form-row">
                            <label for="aaa_category">カテゴリ（任意）</label>
                            <select name="aaa_category" id="aaa_category">
                                <option value="">-- 選択しない --</option>
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category->term_id ); ?>">
                                        <?php echo esc_html( $category->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- A8ショートコード選択（任意） -->
                        <div class="form-row">
                            <label for="aaa_a8_slot">広告ショートコード（任意）</label>
                            <select name="aaa_a8_slot" id="aaa_a8_slot">
                                <option value="">-- 広告を挿入しない --</option>
                                <?php foreach ( $a8_slots as $slot ) : ?>
                                    <option value="<?php echo esc_attr( $slot['slug'] ); ?>">
                                        <?php echo esc_html( $slot['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ( empty( $a8_slots ) ) : ?>
                                <p class="aaa-description">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-auto-affiliate-settings' ) ); ?>">設定ページ</a>でショートコードを登録してください。
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- タグ入力（任意） -->
                    <div class="form-row">
                        <label for="aaa_tags">タグ（任意）</label>
                        <input
                            type="text"
                            name="aaa_tags"
                            id="aaa_tags"
                            placeholder="例: 健康, 生活習慣, おすすめ"
                        />
                        <p class="aaa-description">カンマ区切りで複数のタグを入力できます。</p>
                    </div>

                    <!-- 送信ボタン -->
                    <div class="form-row">
                        <button type="submit" class="button button-primary button-hero aaa-generate-btn">
                            <span class="dashicons dashicons-edit-page" style="margin-right: 5px;"></span>
                            下書きを生成
                        </button>
                    </div>
                </form>
            </div>

            <?php $this->render_recent_drafts(); ?>
        </div>
        <?php
    }

    /**
     * 結果メッセージを表示
     *
     * @param array|false $result 結果データ
     */
    private function render_result_message( $result ) {
        if ( false === $result || ! is_array( $result ) ) {
            return;
        }

        $type    = isset( $result['type'] ) ? $result['type'] : 'info';
        $message = isset( $result['message'] ) ? $result['message'] : '';
        $post_id = isset( $result['post_id'] ) ? absint( $result['post_id'] ) : 0;

        $class = 'notice-' . $type;
        ?>
        <div class="aaa-notice <?php echo esc_attr( $class ); ?>">
            <p><?php echo esc_html( $message ); ?></p>
            <?php if ( $post_id > 0 ) : ?>
                <p>
                    <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-secondary">
                        編集画面を開く
                    </a>
                    <a href="<?php echo esc_url( get_preview_post_link( $post_id ) ); ?>" class="button button-secondary" target="_blank">
                        プレビュー
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * APIキー設定状況の通知
     *
     * @param array $settings 設定値
     */
    private function render_api_status_notice( $settings ) {
        $api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

        if ( empty( $api_key ) ) {
            ?>
            <div class="aaa-notice notice-warning">
                <p>
                    <strong>APIキーが設定されていません。</strong>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-auto-affiliate-settings' ) ); ?>">設定ページ</a>でAPIキーを入力してください。
                </p>
            </div>
            <?php
        }
    }

    /**
     * 最近の下書き一覧を表示
     */
    private function render_recent_drafts() {
        $drafts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'draft',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_key'       => '_aaa_generated',
            'meta_value'     => '1',
        ) );

        if ( empty( $drafts ) ) {
            return;
        }

        ?>
        <div class="aaa-card">
            <h2>AI生成した最近の下書き</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>タイトル</th>
                        <th>作成日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $drafts as $draft ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $draft->post_title ); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html( get_the_date( 'Y/m/d H:i', $draft ) ); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $draft->ID ) ); ?>">編集</a> |
                                <a href="<?php echo esc_url( get_preview_post_link( $draft->ID ) ); ?>" target="_blank">プレビュー</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * POST処理: 記事生成
     */
    public function handle_generate_post() {
        // 権限チェック
        if ( ! current_user_can( AAA_CAPABILITY ) ) {
            wp_die( 'この操作を実行する権限がありません。', 'エラー', array( 'back_link' => true ) );
        }

        // Nonce検証
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            wp_die( 'セキュリティチェックに失敗しました。ページを再読み込みしてもう一度お試しください。', 'エラー', array( 'back_link' => true ) );
        }

        // 入力値のサニタイズ
        $keyword  = isset( $_POST['aaa_keyword'] ) ? sanitize_textarea_field( wp_unslash( $_POST['aaa_keyword'] ) ) : '';
        $category = isset( $_POST['aaa_category'] ) ? absint( $_POST['aaa_category'] ) : 0;
        $tags     = isset( $_POST['aaa_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['aaa_tags'] ) ) : '';
        $a8_slot  = isset( $_POST['aaa_a8_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['aaa_a8_slot'] ) ) : '';

        // キーワード必須チェック
        if ( empty( $keyword ) ) {
            $this->redirect_with_message( 'error', 'キーワード/テーマを入力してください。' );
            return;
        }

        // ログ記録
        $this->log_info( '記事生成を開始', array(
            'keyword'  => mb_strimwidth( $keyword, 0, 50, '...' ),
            'category' => $category,
            'a8_slot'  => $a8_slot,
        ) );

        // 設定を取得
        $settings = aaa_get_settings();

        // APIキーチェック
        if ( empty( $settings['api_key'] ) ) {
            $this->log_error( 'APIキーが設定されていません' );
            $this->redirect_with_message( 'error', 'APIキーが設定されていません。設定ページで入力してください。' );
            return;
        }

        // ============================================
        // 記事生成処理
        // Step D時点ではダミー本文で保存
        // Step E/Fで実際のAI生成＋広告挿入を実装
        // ============================================

        $result = $this->generate_and_save_draft( $keyword, $category, $tags, $a8_slot, $settings );

        if ( is_wp_error( $result ) ) {
            $this->log_error( '記事生成に失敗', array( 'error' => $result->get_error_message() ) );
            $this->redirect_with_message( 'error', '記事の生成に失敗しました: ' . $result->get_error_message() );
            return;
        }

        // 成功
        $post_id = $result;
        $this->log_info( '下書きを作成しました', array( 'post_id' => $post_id ) );
        $this->redirect_with_message( 'success', '下書きを作成しました（ID: ' . $post_id . '）', $post_id );
    }

    /**
     * 記事を生成して下書き保存
     *
     * @param string $keyword  キーワード
     * @param int    $category カテゴリID
     * @param string $tags     タグ（カンマ区切り）
     * @param string $a8_slot  A8スロットslug
     * @param array  $settings 設定値
     * @return int|WP_Error 投稿ID または エラー
     */
    private function generate_and_save_draft( $keyword, $category, $tags, $a8_slot, $settings ) {
        // ============================================
        // Claude API で記事を生成
        // ============================================

        // プロバイダーを初期化
        $provider = new AAA_Provider_Claude();

        // 記事を生成
        $result = $provider->generate_article( array(
            'keyword' => $keyword,
            'a8_slot' => $a8_slot,
        ) );

        // エラーチェック
        if ( ! $result['ok'] ) {
            return new WP_Error( 'generation_failed', $result['error'] );
        }

        // 生成結果から取得
        $title   = $result['title'];
        $content = $result['content'];
        $related = isset( $result['related'] ) ? $result['related'] : array();

        // 投稿データ
        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft', // 必ずdraft（公開禁止）
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        );

        // カテゴリ設定
        if ( $category > 0 ) {
            $post_data['post_category'] = array( $category );
        }

        // 投稿を挿入
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // タグを設定
        if ( ! empty( $tags ) ) {
            $tag_array = array_map( 'trim', explode( ',', $tags ) );
            $tag_array = array_filter( $tag_array ); // 空要素を除去
            if ( ! empty( $tag_array ) ) {
                wp_set_post_tags( $post_id, $tag_array );
            }
        }

        // AI生成フラグをメタに保存（後で識別用）
        update_post_meta( $post_id, '_aaa_generated', '1' );
        update_post_meta( $post_id, '_aaa_keyword', $keyword );

        // A8スロット情報を保存（Step Fで広告挿入時に使用）
        if ( ! empty( $a8_slot ) ) {
            update_post_meta( $post_id, '_aaa_a8_slot', $a8_slot );
        }

        // 関連記事候補を保存
        if ( ! empty( $related ) ) {
            update_post_meta( $post_id, '_aaa_related_articles', $related );
        }

        return $post_id;
    }

    /**
     * キーワードからタイトルを生成（フォールバック用）
     *
     * @param string $keyword キーワード
     * @return string タイトル
     */
    private function generate_title_from_keyword( $keyword ) {
        // 改行を除去して最初の行を使用
        $first_line = strtok( $keyword, "\n" );
        $first_line = trim( $first_line );

        // 長すぎる場合は切り詰め
        if ( mb_strlen( $first_line ) > 60 ) {
            $first_line = mb_substr( $first_line, 0, 60 ) . '...';
        }

        return $first_line . 'について徹底解説';
    }

    /**
     * 結果メッセージ付きでリダイレクト（PRGパターン）
     *
     * @param string $type    メッセージタイプ (success|error|warning|info)
     * @param string $message メッセージ本文
     * @param int    $post_id 投稿ID（成功時）
     */
    private function redirect_with_message( $type, $message, $post_id = 0 ) {
        $user_id = get_current_user_id();

        // トランジェントに結果を保存（30秒間有効）
        set_transient(
            self::TRANSIENT_KEY . $user_id,
            array(
                'type'    => $type,
                'message' => $message,
                'post_id' => $post_id,
            ),
            30
        );

        // 生成ページにリダイレクト
        wp_safe_redirect( admin_url( 'admin.php?page=ai-auto-affiliate' ) );
        exit;
    }

    /**
     * INFOログを記録
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト
     */
    private function log_info( $message, $context = array() ) {
        if ( class_exists( 'AAA_Logger' ) ) {
            AAA_Logger::get_instance()->info( $message, $context );
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
            AAA_Logger::get_instance()->error( $message, $context );
        }
    }
}
