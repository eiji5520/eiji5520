/**
 * AI Auto Affiliate - Admin JavaScript
 *
 * @package AI_Auto_Affiliate
 */

(function($) {
    'use strict';

    /**
     * AI Auto Affiliate Admin Handler
     */
    var AAAAdmin = {

        /**
         * 初期化
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * イベントバインド
         */
        bindEvents: function() {
            // 記事生成フォーム
            $(document).on('submit', '#aaa-generate-form', this.handleGenerate);

            // ショートコード削除
            $(document).on('click', '.aaa-delete-shortcode', this.handleDeleteShortcode);

            // APIキー表示/非表示トグル
            $(document).on('click', '.aaa-api-key-toggle', this.toggleApiKeyVisibility);
        },

        /**
         * 記事生成処理
         */
        handleGenerate: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.aaa-generate-btn');
            var $generating = $('.aaa-generating');
            var $result = $('.aaa-result');

            // バリデーション
            var keyword = $form.find('[name="aaa_keyword"]').val().trim();
            if (!keyword) {
                alert('キーワード/テーマを入力してください。');
                return;
            }

            // UI更新
            $btn.prop('disabled', true);
            $generating.addClass('active');
            $result.removeClass('active success error');

            // AJAX送信
            $.ajax({
                url: aaaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aaa_generate_article',
                    nonce: aaaAdmin.nonce,
                    keyword: keyword,
                    category: $form.find('[name="aaa_category"]').val(),
                    tags: $form.find('[name="aaa_tags"]').val(),
                    a8_slot: $form.find('[name="aaa_a8_slot"]').val()
                },
                timeout: 120000, // 2分タイムアウト
                success: function(response) {
                    $generating.removeClass('active');
                    $btn.prop('disabled', false);

                    if (response.success) {
                        $result
                            .addClass('active success')
                            .html(
                                '<h3>' + aaaAdmin.i18n.success + '</h3>' +
                                '<p>タイトル: ' + AAAAdmin.escapeHtml(response.data.title) + '</p>' +
                                '<div class="aaa-result-link">' +
                                    '<a href="' + response.data.edit_url + '" target="_blank">編集画面を開く</a>' +
                                '</div>'
                            );
                    } else {
                        $result
                            .addClass('active error')
                            .html(
                                '<h3>' + aaaAdmin.i18n.error + '</h3>' +
                                '<p>' + AAAAdmin.escapeHtml(response.data.message) + '</p>'
                            );
                    }
                },
                error: function(xhr, status, error) {
                    $generating.removeClass('active');
                    $btn.prop('disabled', false);

                    var message = aaaAdmin.i18n.error;
                    if (status === 'timeout') {
                        message = 'タイムアウトしました。もう一度お試しください。';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }

                    $result
                        .addClass('active error')
                        .html('<h3>' + aaaAdmin.i18n.error + '</h3><p>' + AAAAdmin.escapeHtml(message) + '</p>');
                }
            });
        },

        /**
         * ショートコード削除処理
         */
        handleDeleteShortcode: function(e) {
            e.preventDefault();

            if (!confirm(aaaAdmin.i18n.confirmDelete)) {
                return;
            }

            var $btn = $(this);
            var slug = $btn.data('slug');
            var nonce = $btn.data('nonce');

            $.ajax({
                url: aaaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aaa_delete_shortcode',
                    nonce: nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.aaa-shortcode-item').fadeOut(300, function() {
                            $(this).remove();
                            // 空の場合のメッセージ表示
                            if ($('.aaa-shortcode-item').length === 0) {
                                $('.aaa-shortcode-list').html(
                                    '<div class="aaa-shortcode-empty">登録されているショートコードはありません。</div>'
                                );
                            }
                        });
                    } else {
                        alert(response.data.message || 'エラーが発生しました。');
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                }
            });
        },

        /**
         * APIキー表示/非表示トグル
         */
        toggleApiKeyVisibility: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $input = $btn.siblings('input');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.text('非表示');
            } else {
                $input.attr('type', 'password');
                $btn.text('表示');
            }
        },

        /**
         * HTMLエスケープ
         */
        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    // DOM Ready
    $(document).ready(function() {
        AAAAdmin.init();
    });

})(jQuery);
