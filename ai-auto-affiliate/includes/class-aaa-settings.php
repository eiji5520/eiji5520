<?php
/**
 * AAA_Settings クラス
 *
 * 設定ページの実装（WordPress Settings API使用）
 *
 * @package AI_Auto_Affiliate
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AAA_Settings
 */
class AAA_Settings {

    /**
     * オプショングループ名
     *
     * @var string
     */
    private $option_group = 'aaa_settings_group';

    /**
     * 設定ページスラッグ
     *
     * @var string
     */
    private $page_slug = 'ai-auto-affiliate-settings';

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_aaa_delete_shortcode', array( $this, 'ajax_delete_shortcode' ) );
    }

    /**
     * 管理メニューを追加
     */
    public function add_menu_pages() {
        // メインメニュー
        add_menu_page(
            'AI Auto Affiliate',
            'AI Auto Affiliate',
            AAA_CAPABILITY,
            'ai-auto-affiliate',
            array( $this, 'render_generator_page' ),
            'dashicons-edit-page',
            30
        );

        // サブメニュー：生成ページ（メインと同じ）
        add_submenu_page(
            'ai-auto-affiliate',
            '記事生成',
            '記事生成',
            AAA_CAPABILITY,
            'ai-auto-affiliate',
            array( $this, 'render_generator_page' )
        );

        // サブメニュー：設定ページ
        add_submenu_page(
            'ai-auto-affiliate',
            '設定',
            '設定',
            AAA_CAPABILITY,
            $this->page_slug,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * 生成ページのレンダリング（プレースホルダー：AAA_Generatorで上書き）
     */
    public function render_generator_page() {
        // AAA_Generator クラスで実装
        echo '<div class="wrap"><h1>記事生成</h1><p>生成ページは別クラスで実装されます。</p></div>';
    }

    /**
     * 設定を登録（Settings API）
     */
    public function register_settings() {
        // 設定を登録
        register_setting(
            $this->option_group,
            AAA_OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => aaa_get_default_settings(),
            )
        );

        // セクション1: AI設定
        add_settings_section(
            'aaa_section_ai',
            'AI設定',
            array( $this, 'render_section_ai' ),
            $this->page_slug
        );

        // Provider
        add_settings_field(
            'aaa_field_provider',
            'AIプロバイダー',
            array( $this, 'render_field_provider' ),
            $this->page_slug,
            'aaa_section_ai'
        );

        // API Key
        add_settings_field(
            'aaa_field_api_key',
            'APIキー',
            array( $this, 'render_field_api_key' ),
            $this->page_slug,
            'aaa_section_ai'
        );

        // Model
        add_settings_field(
            'aaa_field_model',
            'モデル名',
            array( $this, 'render_field_model' ),
            $this->page_slug,
            'aaa_section_ai'
        );

        // セクション2: 記事生成設定
        add_settings_section(
            'aaa_section_article',
            '記事生成設定',
            array( $this, 'render_section_article' ),
            $this->page_slug
        );

        // Template
        add_settings_field(
            'aaa_field_template',
            '記事構成テンプレート',
            array( $this, 'render_field_template' ),
            $this->page_slug,
            'aaa_section_article'
        );

        // Min/Max chars
        add_settings_field(
            'aaa_field_chars',
            '文字数目安',
            array( $this, 'render_field_chars' ),
            $this->page_slug,
            'aaa_section_article'
        );

        // NG Words
        add_settings_field(
            'aaa_field_ng_words',
            'NGワード',
            array( $this, 'render_field_ng_words' ),
            $this->page_slug,
            'aaa_section_article'
        );

        // セクション3: アフィリエイト設定
        add_settings_section(
            'aaa_section_affiliate',
            'アフィリエイト設定',
            array( $this, 'render_section_affiliate' ),
            $this->page_slug
        );

        // PR表記
        add_settings_field(
            'aaa_field_pr_label',
            'PR表記',
            array( $this, 'render_field_pr_label' ),
            $this->page_slug,
            'aaa_section_affiliate'
        );

        // A8 Shortcodes
        add_settings_field(
            'aaa_field_a8_slots',
            'A8ショートコード管理',
            array( $this, 'render_field_a8_slots' ),
            $this->page_slug,
            'aaa_section_affiliate'
        );
    }

    /**
     * 設定値のサニタイズ
     *
     * @param array $input 入力値
     * @return array サニタイズ済み値
     */
    public function sanitize_settings( $input ) {
        $current  = aaa_get_settings();
        $defaults = aaa_get_default_settings();
        $output   = array();

        // Provider（許可リストでチェック）
        $allowed_providers  = array( 'claude', 'openai' );
        $output['provider'] = isset( $input['provider'] ) && in_array( $input['provider'], $allowed_providers, true )
            ? $input['provider']
            : $defaults['provider'];

        // API Key（空でなければ更新、空なら既存値を維持）
        if ( isset( $input['api_key'] ) && '' !== $input['api_key'] ) {
            // 伏字（●）が含まれていたら既存値を維持
            if ( strpos( $input['api_key'], '•' ) !== false ) {
                $output['api_key'] = $current['api_key'];
            } else {
                $output['api_key'] = sanitize_text_field( $input['api_key'] );
            }
        } else {
            $output['api_key'] = $current['api_key'];
        }

        // Model
        $output['model'] = isset( $input['model'] )
            ? sanitize_text_field( $input['model'] )
            : $defaults['model'];

        // Template
        $output['template'] = isset( $input['template'] )
            ? sanitize_textarea_field( $input['template'] )
            : $defaults['template'];

        // Min chars（数値、0以上）
        $output['min_chars'] = isset( $input['min_chars'] )
            ? absint( $input['min_chars'] )
            : $defaults['min_chars'];

        // Max chars（数値、min_chars以上）
        $output['max_chars'] = isset( $input['max_chars'] )
            ? absint( $input['max_chars'] )
            : $defaults['max_chars'];

        // max が min より小さい場合は調整
        if ( $output['max_chars'] < $output['min_chars'] ) {
            $output['max_chars'] = $output['min_chars'];
        }

        // NG Words
        $output['ng_words'] = isset( $input['ng_words'] )
            ? sanitize_textarea_field( $input['ng_words'] )
            : $defaults['ng_words'];

        // PR Label（チェックボックス）
        $output['show_pr_label'] = isset( $input['show_pr_label'] ) && '1' === $input['show_pr_label'];

        // A8 Slots（既存のスロットを維持 + 新規追加）
        $output['a8_slots'] = isset( $current['a8_slots'] ) ? $current['a8_slots'] : array();

        // 新規ショートコード追加
        if ( ! empty( $input['new_a8_slug'] ) && ! empty( $input['new_a8_name'] ) && ! empty( $input['new_a8_code'] ) ) {
            $new_slug = sanitize_key( $input['new_a8_slug'] );
            $new_name = sanitize_text_field( $input['new_a8_name'] );
            $new_code = wp_kses_post( $input['new_a8_code'] ); // HTMLを許可

            // slug重複チェック
            $slug_exists = false;
            foreach ( $output['a8_slots'] as $slot ) {
                if ( $slot['slug'] === $new_slug ) {
                    $slug_exists = true;
                    break;
                }
            }

            if ( ! $slug_exists && '' !== $new_slug ) {
                $output['a8_slots'][] = array(
                    'slug' => $new_slug,
                    'name' => $new_name,
                    'code' => $new_code,
                );

                // 追加成功メッセージ
                add_settings_error(
                    AAA_OPTION_NAME,
                    'a8_added',
                    sprintf( 'ショートコード「%s」を追加しました。', esc_html( $new_name ) ),
                    'success'
                );
            } elseif ( $slug_exists ) {
                add_settings_error(
                    AAA_OPTION_NAME,
                    'a8_duplicate',
                    sprintf( 'スラッグ「%s」は既に存在します。', esc_html( $new_slug ) ),
                    'error'
                );
            }
        }

        // ログ記録
        if ( class_exists( 'AAA_Logger' ) ) {
            AAA_Logger::get_instance()->info( '設定を保存しました', array(
                'provider'      => $output['provider'],
                'model'         => $output['model'],
                'min_chars'     => $output['min_chars'],
                'max_chars'     => $output['max_chars'],
                'show_pr_label' => $output['show_pr_label'],
                'a8_slots_count' => count( $output['a8_slots'] ),
            ) );
        }

        return $output;
    }

    /**
     * セクション: AI設定
     */
    public function render_section_ai() {
        echo '<p>AIプロバイダーの設定を行います。現在はClaude（Anthropic）に対応しています。</p>';
    }

    /**
     * セクション: 記事生成設定
     */
    public function render_section_article() {
        echo '<p>生成される記事の構成や制約を設定します。</p>';
    }

    /**
     * セクション: アフィリエイト設定
     */
    public function render_section_affiliate() {
        echo '<p>A8.netなどのアフィリエイトショートコードを管理します。広告は記事内に1箇所のみ挿入されます。</p>';
    }

    /**
     * フィールド: Provider
     */
    public function render_field_provider() {
        $settings = aaa_get_settings();
        $provider = $settings['provider'];
        ?>
        <select name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[provider]" id="aaa_provider">
            <option value="claude" <?php selected( $provider, 'claude' ); ?>>Claude (Anthropic)</option>
            <option value="openai" <?php selected( $provider, 'openai' ); ?> disabled>OpenAI (準備中)</option>
        </select>
        <p class="aaa-description">使用するAIプロバイダーを選択してください。</p>
        <?php
    }

    /**
     * フィールド: API Key
     */
    public function render_field_api_key() {
        $settings = aaa_get_settings();
        $api_key  = $settings['api_key'];
        $masked   = aaa_mask_api_key( $api_key );
        ?>
        <div class="aaa-api-key-field">
            <input
                type="password"
                name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[api_key]"
                id="aaa_api_key"
                value="<?php echo esc_attr( $masked ); ?>"
                class="regular-text"
                autocomplete="off"
            />
            <button type="button" class="aaa-api-key-toggle">表示</button>
        </div>
        <p class="aaa-description">
            APIキーは安全に保存されます。新しいキーを入力すると上書きされます。<br>
            <strong>注意:</strong> キーを変更しない場合は、このフィールドを空のままにしてください。
        </p>
        <?php
    }

    /**
     * フィールド: Model
     */
    public function render_field_model() {
        $settings = aaa_get_settings();
        $model    = $settings['model'];
        ?>
        <input
            type="text"
            name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[model]"
            id="aaa_model"
            value="<?php echo esc_attr( $model ); ?>"
            class="regular-text"
        />
        <p class="aaa-description">
            使用するモデル名を入力してください。<br>
            Claude例: <code>claude-sonnet-4-20250514</code>, <code>claude-3-5-haiku-20241022</code>
        </p>
        <?php
    }

    /**
     * フィールド: Template
     */
    public function render_field_template() {
        $settings = aaa_get_settings();
        $template = $settings['template'];
        ?>
        <textarea
            name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[template]"
            id="aaa_template"
            rows="12"
            class="large-text code"
        ><?php echo esc_textarea( $template ); ?></textarea>
        <p class="aaa-description">
            記事の構成テンプレートです。<code>{{AD_INSERTION_POINT}}</code> の位置に広告が挿入されます。<br>
            この変数がない場合、「おすすめの解決策」セクションの直下に挿入されます。
        </p>
        <?php
    }

    /**
     * フィールド: Min/Max chars
     */
    public function render_field_chars() {
        $settings  = aaa_get_settings();
        $min_chars = $settings['min_chars'];
        $max_chars = $settings['max_chars'];
        ?>
        <div style="display: flex; gap: 20px; align-items: center;">
            <label>
                最小:
                <input
                    type="number"
                    name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[min_chars]"
                    id="aaa_min_chars"
                    value="<?php echo esc_attr( $min_chars ); ?>"
                    min="500"
                    max="10000"
                    step="100"
                    style="width: 100px;"
                />
                文字
            </label>
            <label>
                最大:
                <input
                    type="number"
                    name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[max_chars]"
                    id="aaa_max_chars"
                    value="<?php echo esc_attr( $max_chars ); ?>"
                    min="500"
                    max="10000"
                    step="100"
                    style="width: 100px;"
                />
                文字
            </label>
        </div>
        <p class="aaa-description">生成される記事の目安文字数です。</p>
        <?php
    }

    /**
     * フィールド: NG Words
     */
    public function render_field_ng_words() {
        $settings = aaa_get_settings();
        $ng_words = $settings['ng_words'];
        ?>
        <textarea
            name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[ng_words]"
            id="aaa_ng_words"
            rows="5"
            class="large-text"
        ><?php echo esc_textarea( $ng_words ); ?></textarea>
        <p class="aaa-description">
            記事に含めたくないNGワードを改行区切りで入力してください。<br>
            例: 絶対、確実、100%、必ず（誇大表現の防止）
        </p>
        <?php
    }

    /**
     * フィールド: PR Label
     */
    public function render_field_pr_label() {
        $settings      = aaa_get_settings();
        $show_pr_label = $settings['show_pr_label'];
        ?>
        <label>
            <input
                type="checkbox"
                name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[show_pr_label]"
                id="aaa_show_pr_label"
                value="1"
                <?php checked( $show_pr_label, true ); ?>
            />
            広告挿入箇所に「※PRを含みます」を表示する
        </label>
        <p class="aaa-description">
            アフィリエイトリンクを含む場合、PR表記を自動挿入します（景品表示法対応）。
        </p>
        <?php
    }

    /**
     * フィールド: A8 Slots
     */
    public function render_field_a8_slots() {
        $settings = aaa_get_settings();
        $a8_slots = isset( $settings['a8_slots'] ) ? $settings['a8_slots'] : array();
        ?>
        <!-- 登録済みショートコード一覧 -->
        <div class="aaa-shortcode-list">
            <?php if ( empty( $a8_slots ) ) : ?>
                <div class="aaa-shortcode-empty">
                    登録されているショートコードはありません。
                </div>
            <?php else : ?>
                <?php foreach ( $a8_slots as $slot ) : ?>
                    <div class="aaa-shortcode-item">
                        <div class="aaa-shortcode-info">
                            <span class="aaa-shortcode-slug"><?php echo esc_html( $slot['slug'] ); ?></span>
                            <span class="aaa-shortcode-name"><?php echo esc_html( $slot['name'] ); ?></span>
                            <div class="aaa-shortcode-code">
                                <?php echo esc_html( mb_strimwidth( $slot['code'], 0, 100, '...' ) ); ?>
                            </div>
                        </div>
                        <div class="aaa-shortcode-actions">
                            <button
                                type="button"
                                class="button button-secondary aaa-delete-shortcode"
                                data-slug="<?php echo esc_attr( $slot['slug'] ); ?>"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'aaa_delete_shortcode_' . $slot['slug'] ) ); ?>"
                            >
                                削除
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 新規追加フォーム -->
        <div class="aaa-add-shortcode">
            <h3>新規ショートコード追加</h3>

            <div class="form-row">
                <label for="aaa_new_a8_slug">スラッグ（識別子）</label>
                <input
                    type="text"
                    name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[new_a8_slug]"
                    id="aaa_new_a8_slug"
                    placeholder="例: product-a"
                    pattern="[a-z0-9-]+"
                    class="regular-text"
                />
                <p class="aaa-description">半角英数字とハイフンのみ使用可能</p>
            </div>

            <div class="form-row">
                <label for="aaa_new_a8_name">表示名</label>
                <input
                    type="text"
                    name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[new_a8_name]"
                    id="aaa_new_a8_name"
                    placeholder="例: おすすめ商品A"
                    class="regular-text"
                />
            </div>

            <div class="form-row">
                <label for="aaa_new_a8_code">ショートコード / HTML</label>
                <textarea
                    name="<?php echo esc_attr( AAA_OPTION_NAME ); ?>[new_a8_code]"
                    id="aaa_new_a8_code"
                    rows="4"
                    placeholder="例: [a8_affiliate id='xxxxx']広告内容[/a8_affiliate]"
                    class="large-text"
                ></textarea>
                <p class="aaa-description">A8.netなどで取得したショートコードまたはHTMLを貼り付けてください。</p>
            </div>
        </div>
        <?php
    }

    /**
     * 設定ページのレンダリング
     */
    public function render_settings_page() {
        // 権限チェック
        if ( ! current_user_can( AAA_CAPABILITY ) ) {
            wp_die( 'この操作を実行する権限がありません。' );
        }
        ?>
        <div class="wrap aaa-wrap">
            <h1>AI Auto Affiliate 設定</h1>

            <?php settings_errors( AAA_OPTION_NAME ); ?>

            <form method="post" action="options.php" class="aaa-settings-form">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( $this->page_slug );
                submit_button( '設定を保存' );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX: ショートコード削除
     */
    public function ajax_delete_shortcode() {
        // 権限チェック
        if ( ! current_user_can( AAA_CAPABILITY ) ) {
            wp_send_json_error( array( 'message' => '権限がありません。' ) );
        }

        // パラメータ取得
        $slug  = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        // nonceチェック
        if ( ! wp_verify_nonce( $nonce, 'aaa_delete_shortcode_' . $slug ) ) {
            wp_send_json_error( array( 'message' => 'セキュリティチェックに失敗しました。' ) );
        }

        if ( empty( $slug ) ) {
            wp_send_json_error( array( 'message' => 'スラッグが指定されていません。' ) );
        }

        // 設定を取得
        $settings = aaa_get_settings();
        $a8_slots = isset( $settings['a8_slots'] ) ? $settings['a8_slots'] : array();

        // スラッグで検索して削除
        $found = false;
        foreach ( $a8_slots as $index => $slot ) {
            if ( $slot['slug'] === $slug ) {
                unset( $a8_slots[ $index ] );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            wp_send_json_error( array( 'message' => '指定されたショートコードが見つかりません。' ) );
        }

        // 配列を再インデックス
        $a8_slots = array_values( $a8_slots );

        // 保存
        $settings['a8_slots'] = $a8_slots;
        update_option( AAA_OPTION_NAME, $settings );

        // ログ記録
        if ( class_exists( 'AAA_Logger' ) ) {
            AAA_Logger::get_instance()->info( 'ショートコードを削除しました', array( 'slug' => $slug ) );
        }

        wp_send_json_success( array( 'message' => '削除しました。' ) );
    }
}
