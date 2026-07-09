(function () {
    function focusNewsEditor(shell) {
        var editor = shell.querySelector('.ProseMirror');

        if (!editor || editor.contains(document.activeElement)) {
            return;
        }

        editor.focus({ preventScroll: true });

        if (window.getSelection && document.createRange) {
            var range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            var selection = window.getSelection();

            if (selection) {
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }
    }

    document.addEventListener('mousedown', function (event) {
        if (event.target.closest('.fi-fo-rich-editor-toolbar')) {
            return;
        }

        var shell = event.target.closest('.news-content-rich-editor .fi-fo-rich-editor-content');

        if (!shell) {
            return;
        }

        var editor = shell.querySelector('.ProseMirror');

        if (!editor || editor.contains(event.target)) {
            return;
        }

        event.preventDefault();
        focusNewsEditor(shell);
    });
})();
