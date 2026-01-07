jQuery(document).ready(function ($) {
    if ($('body').hasClass('post-type-upbp_pattern')) {

        // 1. Inject "Import from JSON" button
        var $addNewBtn = $('.page-title-action').last();
        if ($addNewBtn.length) {
            $addNewBtn.after('<a href="#" id="ubpb-import-btn" class="page-title-action ubpb-import-trigger">' + ubpbConfig.strings.importBtn + '</a>');
        }

        // 2. Create Modal Markup
        var modalMarkup = `
            <div id="ubpb-import-modal" class="ubpb-modal-overlay" style="display:none;">
                <div class="ubpb-modal">
                    <div class="ubpb-modal-header">
                        <h2>${ubpbConfig.strings.importTitle}</h2>
                        <button class="ubpb-modal-close">&times;</button>
                    </div>
                    <div class="ubpb-modal-body">
                         <div class="ubpb-file-upload-area" id="ubpb-drop-zone">
                            <span class="dashicons dashicons-upload" style="font-size: 40px; width: 40px; height: 40px; color: #ccc; margin-bottom: 15px;"></span>
                            <p>${ubpbConfig.strings.importPlaceholder}</p>
                            <input type="file" id="ubpb-import-file" accept=".json" style="display:none;">
                            <button class="button" id="ubpb-select-file-btn">${ubpbConfig.strings.selectFile}</button>
                            <div id="ubpb-file-name" style="margin-top: 10px; font-weight: 600; color: #2271b1;"></div>
                        </div>
                        <div id="ubpb-import-status"></div>
                    </div>
                    <div class="ubpb-modal-footer">
                        <button class="button button-large ubpb-modal-cancel">${ubpbConfig.strings.cancel}</button>
                        <button class="button button-primary button-large" id="ubpb-do-import" disabled>${ubpbConfig.strings.import}</button>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modalMarkup);

        // 3. Events
        var $modal = $('#ubpb-import-modal');
        var $fileInput = $('#ubpb-import-file');
        var $status = $('#ubpb-import-status');
        var $importBtn = $('#ubpb-do-import');
        var $dropZone = $('#ubpb-drop-zone');
        var $fileName = $('#ubpb-file-name');

        function handleFileSelect(file) {
            if (file && file.type === "application/json") {
                $fileName.text(file.name);
                $importBtn.prop('disabled', false);
                $status.html('').removeClass('error');
            } else {
                $status.addClass('error').text(ubpbConfig.strings.invalidFile);
                $importBtn.prop('disabled', true);
            }
        }

        $(document).on('click', '#ubpb-import-btn', function (e) {
            e.preventDefault();
            $modal.css('display', 'flex').hide().fadeIn(200);
        });

        $(document).on('click', '#ubpb-select-file-btn', function (e) {
            e.preventDefault();
            $fileInput.click();
        });

        $fileInput.on('change', function (e) {
            var file = e.target.files[0];
            handleFileSelect(file);
        });

        // Drag and drop events
        $dropZone.on('dragover', function (e) {
            e.preventDefault();
            $(this).addClass('dragover');
        }).on('dragleave drop', function (e) {
            e.preventDefault();
            $(this).removeClass('dragover');
        });

        $dropZone.on('drop', function (e) {
            var file = e.originalEvent.dataTransfer.files[0];
            $fileInput[0].files = e.originalEvent.dataTransfer.files; // Update input
            handleFileSelect(file);
        });

        $(document).on('click', '.ubpb-modal-close, .ubpb-modal-cancel', function (e) {
            e.preventDefault();
            $modal.fadeOut(200);
            $fileInput.val('');
            $fileName.text('');
            $importBtn.prop('disabled', true);
            $status.html('').removeClass('error success');
        });

        // Close on outside click
        $modal.on('click', function (e) {
            if ($(e.target).is('#ubpb-import-modal')) {
                $modal.fadeOut(200);
                $fileInput.val('');
                $fileName.text('');
            }
        });

        $('#ubpb-do-import').on('click', function (e) {
            e.preventDefault();
            var file = $fileInput[0].files[0];
            if (!file) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text(ubpbConfig.strings.importing);
            $status.html('');

            var formData = new FormData();
            formData.append('action', 'ubpb_import_pattern');
            formData.append('nonce', ubpbConfig.nonce);
            formData.append('import_file', file);

            $.ajax({
                url: ubpbConfig.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        $btn.text(ubpbConfig.strings.import);
                        $status.addClass('success').text(ubpbConfig.strings.success);
                        setTimeout(function () {
                            if (response.data) {
                                window.location.href = response.data;
                            } else {
                                window.location.reload();
                            }
                        }, 1000);
                    } else {
                        $btn.prop('disabled', false).text(ubpbConfig.strings.import);
                        $status.addClass('error').text(response.data || ubpbConfig.strings.error);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).text(ubpbConfig.strings.import);
                    $status.addClass('error').text(ubpbConfig.strings.error);
                }
            });
        });
    }
});
