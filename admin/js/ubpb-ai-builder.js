jQuery(document).ready(function ($) {
    // 1. API Key Setup
    $('#ubpb-save-api-key').on('click', function (e) {
        e.preventDefault();
        var key = $('#ubpb_api_key_input').val().trim();
        if (!key) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text(ubpbAI.strings.saving);

        $.post(ubpbAI.ajaxUrl, {
            action: 'ubpb_save_api_key',
            nonce: ubpbAI.nonce,
            key: key
        }, function (response) {
            if (response.success) {
                $btn.text(ubpbAI.strings.saved);
                location.reload();
            } else {
                $btn.prop('disabled', false).text('Save');
                alert(ubpbAI.strings.error);
            }
        });
    });

    $('#ubpb-reset-key').on('click', function (e) {
        if (confirm('Are you sure you want to reset the API Key?')) {
            $.post(ubpbAI.ajaxUrl, {
                action: 'ubpb_save_api_key',
                nonce: ubpbAI.nonce,
                key: ''
            }, function () {
                location.reload();
            });
        }
    });

    // 2. Chat Interface
    var $input = $('#ubpb-chat-input');
    var $chatWindow = $('#ubpb-chat-window');
    var $sendBtn = $('#ubpb-send-prompt');

    // Auto resize textarea
    $input.on('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        if (this.value === '') this.style.height = 'auto';
    });

    // Send on Enter (shift+enter for new line)
    $input.on('keydown', function (e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    $sendBtn.on('click', sendMessage);

    function sendMessage() {
        var prompt = $input.val().trim();
        if (!prompt) return;

        // Add User Message
        addMessage('user', prompt);
        $input.val('').css('height', 'auto');

        // Show Loading
        showTyping();

        // AJAX
        $.post(ubpbAI.ajaxUrl, {
            action: 'ubpb_generate_pattern',
            nonce: ubpbAI.nonce,
            prompt: prompt
        }, function (response) {
            removeTyping();

            if (response.success) {
                var content = response.data.content;
                var preview = response.data.preview || content; // Fallback to content if no preview
                addAssistantPreview(content, preview);
            } else {
                addMessage('system', 'Error: ' + response.data);
            }
        }).fail(function () {
            removeTyping();
            addMessage('system', ubpbAI.strings.error);
        });
    }

    function addMessage(role, text) {
        var html = `
            <div class="ubpb-message ${role}">
                <div class="ubpb-msg-content">${escapeHtml(text)}</div>
            </div>
            <div style="clear:both"></div>
        `;
        $chatWindow.append(html);
        scrollToBottom();
    }

    function addAssistantPreview(content, preview) {
        var id = 'pattern-' + Date.now();

        var html = `
            <div class="ubpb-message assistant">
                <div class="ubpb-ai-result-preview">
                    <div class="ubpb-preview-header">
                        ${ubpbAI.strings.generating.replace('...', '')} Result
                    </div>
                    <div class="ubpb-preview-content">
                        ${preview} 
                    </div>
                    <div class="ubpb-preview-actions">
                         <button class="button button-primary ubpb-save-ai-pattern" data-content="${encodeURIComponent(content)}">
                             ${ubpbAI.strings.import}
                         </button>
                    </div>
                </div>
            </div>
             <div style="clear:both"></div>
        `;
        $chatWindow.append(html);
        scrollToBottom();
    }

    function showTyping() {
        var html = `
            <div class="ubpb-typing-indicator" id="ubpb-typing">
                 <span>AI is thinking</span>
                 <div class="ubpb-dots"><span></span><span></span><span></span></div>
            </div>
        `;
        $chatWindow.append(html);
        scrollToBottom();
    }

    function removeTyping() {
        $('#ubpb-typing').remove();
    }

    function scrollToBottom() {
        $chatWindow.animate({ scrollTop: $chatWindow[0].scrollHeight }, 300);
    }

    function escapeHtml(text) {
        if (!text) return text;
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;")
            .replace(/\n/g, "<br>");
    }

    // 3. Save AI Pattern
    $(document).on('click', '.ubpb-save-ai-pattern', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var content = decodeURIComponent($btn.data('content'));

        $btn.prop('disabled', true).text(ubpbAI.strings.importing);

        $.post(ubpbAI.ajaxUrl, {
            action: 'ubpb_add_ai_pattern',
            nonce: ubpbAI.nonce,
            content: content
        }, function (response) {
            if (response.success) {
                $btn.text(ubpbAI.strings.imported);
                // Maybe show link?
                $btn.replaceWith(`<a href="${response.data}" target="_blank" class="button button-secondary">Edit Saved Pattern</a>`);
            } else {
                alert(response.data);
                $btn.prop('disabled', false).text(ubpbAI.strings.import);
            }
        });
    });

});
