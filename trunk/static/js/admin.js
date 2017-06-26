//加载完成后处理事件
$(function () {

    if ($(".kindeditor").size() > 0 && typeof KindEditor != "undefined") {
        var editor;
        KindEditor.ready(function (K) {
            $(".kindeditor").each(function () {
                editor = K.create("#" + $(this).prop("id"), {
                    items: ['source', '|', 'undo', 'redo', '|', 'preview', 'print', 'template', 'code', 'cut', 'copy', 'paste',
                        'plainpaste', 'wordpaste', '|', 'justifyleft', 'justifycenter', 'justifyright',
                        'justifyfull', 'insertorderedlist', 'insertunorderedlist', 'indent', 'outdent', 'subscript',
                        'superscript', 'clearhtml', 'quickformat', 'selectall', '|', 'fullscreen', '/',
                        'formatblock', 'fontname', 'fontsize', '|', 'forecolor', 'hilitecolor', 'bold',
                        'italic', 'underline', 'strikethrough', 'lineheight', 'removeformat', '|',
                        'flash', 'media', 'insertfile', 'table', 'hr', 'emoticons', 'baidumap', 'pagebreak',
                        'anchor', 'link', 'unlink', 'photoshop'],
                    allowImageUpload: false
                });
                //K.insertHtml('#sale_protection', '<strong>HTML</strong>');

            });
            KindEditor.plugin('photoshop', function (K) {
                $.each(KindEditor.instances, function () {
                    var _editor = this;
                    // 点击图标时执行
                    _editor.clickToolbar('photoshop', function () {
                        var index = $(".kindeditor").index($(this.options.srcElement));
                        art.dialog.open(ADMIN.photoshop_url + '?type=editor&from=kindeditor&index=' + index + '&id=' + $(this.options.srcElement).prop("id"), {id: 'upimg_dialog', lock: true, opacity: 0.1, title: '选择图片', width: 613, height: 380});
                    });
                });
            });
        });
    }

});