/**
 * Blog Agent Admin JavaScript
 *
 * @package Blog_Agent
 */

(function($) {
    'use strict';

    const BlogAgent = {
        init: function() {
            this.bindEvents();
            this.initProviderToggle();
            this.initAutopostModeToggle();
        },

        bindEvents: function() {
            // Test connection button
            $('#test-connection').on('click', this.testConnection.bind(this));

            // Emergency stop button
            $('#emergency-stop').on('click', this.emergencyStop.bind(this));

            // Generate plan button
            $('#generate-plan').on('click', this.generatePlan.bind(this));

            // Provider change
            $('#blog_agent_provider').on('change', this.handleProviderChange.bind(this));

            // Autopost mode change
            $('#blog_agent_autopost_mode').on('change', this.handleAutopostModeChange.bind(this));

            // Select all articles checkbox
            $('#select-all-articles').on('change', this.selectAllArticles.bind(this));

            // Queue selected articles
            $('#queue-selected').on('click', this.queueSelected.bind(this));

            // Generate selected articles
            $('#generate-selected').on('click', this.generateSelected.bind(this));

            // Generate single article
            $('.generate-single').on('click', this.generateSingle.bind(this));

            // Run QA button
            $(document).on('click', '.run-qa-btn', this.runQA.bind(this));

            // Approve article button
            $(document).on('click', '.approve-article-btn', this.approveArticle.bind(this));
        },

        initProviderToggle: function() {
            const provider = $('#blog_agent_provider').val();
            this.toggleProviderFields(provider);
        },

        initAutopostModeToggle: function() {
            const mode = $('#blog_agent_autopost_mode').val();
            this.toggleScheduleFields(mode);
        },

        handleProviderChange: function(e) {
            this.toggleProviderFields($(e.target).val());
        },

        handleAutopostModeChange: function(e) {
            this.toggleScheduleFields($(e.target).val());
        },

        toggleProviderFields: function(provider) {
            if (provider === 'openai') {
                $('.openai-settings').show();
                $('.gemini-settings').hide();
                $('.openai-model').attr('name', 'blog_agent_model');
                $('.gemini-model').removeAttr('name');
            } else {
                $('.openai-settings').hide();
                $('.gemini-settings').show();
                $('.gemini-model').attr('name', 'blog_agent_model');
                $('.openai-model').removeAttr('name');
            }
        },

        toggleScheduleFields: function(mode) {
            if (mode === 'schedule') {
                $('.schedule-settings').show();
            } else {
                $('.schedule-settings').hide();
            }
        },

        testConnection: function(e) {
            const $button = $(e.target);
            const $result = $('#test-result');
            const provider = $('#blog_agent_provider').val();

            $button.prop('disabled', true);
            $result.removeClass('success error').text(blogAgentAdmin.strings.testing);

            $.ajax({
                url: blogAgentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blog_agent_test_connection',
                    nonce: blogAgentAdmin.nonce,
                    provider: provider
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(response.data);
                    } else {
                        $result.addClass('error').text(response.data);
                    }
                },
                error: function() {
                    $result.addClass('error').text(blogAgentAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        emergencyStop: function(e) {
            if (!confirm(blogAgentAdmin.strings.confirmStop)) {
                return;
            }

            const $button = $(e.target);
            $button.prop('disabled', true).text(blogAgentAdmin.strings.stopping);

            $.ajax({
                url: blogAgentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blog_agent_emergency_stop',
                    nonce: blogAgentAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert(blogAgentAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Emergency Stop');
                }
            });
        },

        generatePlan: function(e) {
            const $button = $(e.target);
            const projectId = $button.data('project-id');
            const $status = $('#plan-status');

            if (!projectId) {
                alert('Please save the project first.');
                return;
            }

            $button.prop('disabled', true);
            $status.text(blogAgentAdmin.strings.generating);

            $.ajax({
                url: blogAgentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blog_agent_generate_plan',
                    nonce: blogAgentAdmin.nonce,
                    project_id: projectId
                },
                success: function(response) {
                    if (response.success) {
                        $status.text(response.data.message);
                        // Reload to show new plan
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $status.text('Error: ' + response.data);
                    }
                },
                error: function() {
                    $status.text(blogAgentAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        selectAllArticles: function(e) {
            const checked = $(e.target).prop('checked');
            $('input[name="indices[]"]').prop('checked', checked);
        },

        queueSelected: function(e) {
            const indices = this.getSelectedIndices();
            if (indices.length === 0) {
                alert('Please select at least one article to queue.');
                return;
            }

            const projectId = $('input[name="project_id"]').val();
            const $button = $(e.target);

            $button.prop('disabled', true);

            $.ajax({
                url: blogAgentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blog_agent_queue_generation',
                    nonce: blogAgentAdmin.nonce,
                    project_id: projectId,
                    indices: indices
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert(blogAgentAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        generateSelected: function(e) {
            const indices = this.getSelectedIndices();
            if (indices.length === 0) {
                alert('Please select at least one article to generate.');
                return;
            }

            const projectId = $('input[name="project_id"]').val();
            const $progress = $('#generation-progress');
            const $progressFill = $progress.find('.progress-fill');
            const $progressText = $progress.find('.progress-text');

            $progress.show();
            $progressFill.css('width', '0%');

            let completed = 0;
            const total = indices.length;

            const generateNext = () => {
                if (completed >= total) {
                    $progressText.text('Complete! Reloading...');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                    return;
                }

                const index = indices[completed];
                $progressText.text('Generating article ' + (completed + 1) + ' of ' + total + '...');

                $.ajax({
                    url: blogAgentAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'blog_agent_generate_article',
                        nonce: blogAgentAdmin.nonce,
                        project_id: projectId,
                        index: index
                    },
                    success: function(response) {
                        completed++;
                        const percent = (completed / total) * 100;
                        $progressFill.css('width', percent + '%');

                        if (!response.success) {
                            console.error('Error generating article:', response.data);
                        }

                        generateNext();
                    },
                    error: function() {
                        completed++;
                        generateNext();
                    }
                });
            };

            generateNext();
        },

        generateSingle: function(e) {
            const $button = $(e.target);
            const projectId = $button.data('project-id');
            const index = $button.data('index');

            $button.prop('disabled', true).text(blogAgentAdmin.strings.generating);

            $.ajax({
                url: blogAgentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blog_agent_generate_article',
                    nonce: blogAgentAdmin.nonce,
                    project_id: projectId,
                    index: index
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Generate Now');
                    }
                },
                error: function() {
                    alert(blogAgentAdmin.strings.error);
                    $button.prop('disabled', false).text('Generate Now');
                }
            });
        },

        runQA: function(e) {
            const $button = $(e.target);
            const postId = $button.data('post-id');
            const $row = $button.closest('tr');
            const $result = $row.find('.qa-result-message');

            $button.prop('disabled', true);

            $.ajax({
                url: blogAgentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blog_agent_run_qa',
                    nonce: blogAgentAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        if ($result.length) {
                            $result.text('Error: ' + response.data);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                },
                error: function() {
                    alert(blogAgentAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        approveArticle: function(e) {
            const $button = $(e.target);
            const postId = $button.data('post-id');
            const $row = $button.closest('tr');

            $button.prop('disabled', true);

            $.ajax({
                url: blogAgentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blog_agent_approve_article',
                    nonce: blogAgentAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert(blogAgentAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        getSelectedIndices: function() {
            const indices = [];
            $('input[name="indices[]"]:checked').each(function() {
                indices.push(parseInt($(this).val(), 10));
            });
            return indices;
        }
    };

    $(document).ready(function() {
        BlogAgent.init();
    });

})(jQuery);
