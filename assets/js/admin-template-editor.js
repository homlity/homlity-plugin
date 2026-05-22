(function ($) {
    var cfg     = window.homlityTemplateEditor || {};
    var editor  = null;
    var current = null;
    var dirty   = false;

    function showNotice(type, msg) {
        var $n = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + msg + '</p></div>');
        $('#homlity-tpl-notices').html($n);
        if (type === 'success') {
            setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 3000);
        }
    }

    function setDirty(val) {
        dirty = val;
        if (current) {
            $('#homlity-tpl-filename').text(dirty ? '* ' + current : current);
        }
    }

    function loadFile(relative) {
        $.post(cfg.ajaxUrl, {
            action : cfg.loadAction,
            nonce  : cfg.loadNonce,
            file   : relative
        }, function (res) {
            if (!res.success) {
                showNotice('error', res.data ? res.data.message : cfg.i18n.errorLoading);
                return;
            }

            var content = res.data.content;

            if (!editor) {
                $('#homlity-tpl-placeholder').hide();
                $('#homlity-tpl-editor-area').show();
                editor = wp.codeEditor.initialize(
                    document.getElementById('homlity-tpl-content'),
                    cfg.editorSettings
                );
                editor.codemirror.on('change', function () { setDirty(true); });
            }

            editor.codemirror.setValue(content);
            editor.codemirror.clearHistory();
            editor.codemirror.refresh();

            current = relative;
            setDirty(false);

            $('#homlity-tpl-filename').text(relative);
            $('#homlity-tpl-save').prop('disabled', false);
            $('.homlity-file-link').removeClass('active');
            $('[data-file="' + CSS.escape(relative) + '"]').addClass('active');
        }).fail(function () {
            showNotice('error', cfg.i18n.connError);
        });
    }

    function saveFile() {
        if (!current || !editor) { return; }

        var $btn    = $('#homlity-tpl-save');
        var content = editor.codemirror.getValue();

        $btn.prop('disabled', true).text(cfg.i18n.saving);

        $.post(cfg.ajaxUrl, {
            action  : cfg.saveAction,
            nonce   : cfg.saveNonce,
            file    : current,
            content : content
        }, function (res) {
            if (res.success) {
                setDirty(false);
                showNotice('success', cfg.i18n.saved);
            } else {
                showNotice('error', res.data ? res.data.message : cfg.i18n.errorSaving);
            }
        }).fail(function () {
            showNotice('error', cfg.i18n.connError);
        }).always(function () {
            $btn.prop('disabled', false).text(cfg.i18n.saveBtn);
        });
    }

    $(document).on('click', '.homlity-file-link', function (e) {
        e.preventDefault();
        var file = $(this).data('file');
        if (file === current) { return; }
        if (dirty && !confirm(cfg.i18n.unsavedChanges)) { return; }
        loadFile(file);
    });

    $('#homlity-tpl-save').on('click', saveFile);

    $(document).on('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveFile();
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

}(jQuery));
