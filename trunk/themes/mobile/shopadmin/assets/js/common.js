var app = {};

$(function () {
    var loading = false;  //状态标记
    $(document.body).infinite().on("infinite", function () {
        if (loading)
            return;
        loading = true;
        $(".weui-infinite-scroll").show();
        if ($(".pagelist .current").next().is("a") && $(".pagelist .current").next().attr("href")) {
            console.log(111);
            $.ajax({
                url: $(".pagelist .current").next().attr("href"),
                dataType: 'json',
                success: function (ret) {
                    if (ret.contentlist) {
                        $(ret.contentlist).insertBefore($(".weui-infinite-scroll"));
                    } else {
                        $('<div class="no-more-record">暂无更多记录</div>').insertBefore($(".weui-infinite-scroll"));
                    }
                    $(".pagelist").html(ret.pagelist);
                    loading = false;
                    $(".weui-infinite-scroll").hide();
                },
                error: function () {
                    $(".weui-infinite-scroll").hide();
                    $(".pagelist").hide();
                }
            });
        } else {
            loading = false;
            $(".weui-infinite-scroll").hide();
            $(".pagelist").hide();
        }
    });
    if ($(".pagelist a").size() == 0) {
        $(".weui-infinite-scroll").hide();
    }
    //删除按钮事件
    $(document).on("click", ".weui_btn_del", function () {
        var url = $(this).data("url");
        $.confirm("确认删除该条记录?", "确认删除?", function () {
            location.href = url;
        }, function () {
            //取消操作
        });
    });
    //搜索按钮事件
    $(document).on("click", ".searchbtn", function () {
        $("#searchbar").toggle();
        if ($("#searchbar").css("display") == "block") {
            $(document).scrollTop(0);
            $("#search_text").trigger("click");
        }
    });
});