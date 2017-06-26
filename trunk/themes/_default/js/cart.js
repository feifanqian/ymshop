
function changeInfo(id, num) {
    $.post(CART.changeinfo_url, {id: id, num: num}, function (data) {
        var total = 0.00;
        for (var i in data)
            total += parseFloat(data[i]['amount']);
        $("#" + id).find(".amount").text(data[id]['amount']);
        $("#" + id).find(".prom").text(data[id]['prom']);
        if (parseInt($("#" + id).find("input").val()) > data[id]['store_nums']) {
            $("#" + id).find("input").val(data[id]['store_nums']);
            var parent = $("#" + id).find("input").parent().parent();
            if (parent.find(".msg-simple-error").size() == 0)
                parent.append("<div class='msg-simple-error'>最多购买" + data[id]['store_nums'] + "件</div>");
        } else {
            $("#" + id).find("input").val(data[id]['num']);
            $("#" + id).find("input").parent().parent().find(".msg-simple-error").remove();
        }
        $(".cart-total").text(total.toFixed(2));
    }, "json");
}

function check_cart_empty() {
    if ($(".carttable tbody tr").size() == 0) {
        $("#cart_all").empty();
        //2.加
        var str = "<div class='mt20 mb20 p20 box'><p class='cart-empty ie6png'>购物车内暂时没有商品，<a href='/'>去首页</a> 挑选喜欢的商品。</p></div><div class='mt10 clearfix'><p class='fr'><a class='btn btn-main' href='/'> < 继续购物</a></p></div>";
        $("#cart_all").append(str);
    }
}

$(function () {
    //选中全部
    $(".check-all").on("click", function () {
        var flag = $(this).is(":checked");
        if (flag) {
            $("input[type='checkbox']").prop("checked", true);
        } else {
            $("input[type='checkbox']").prop("checked", false);
        }
        changeInfo(0, 0);
    });

    $(".check-one").on('click', function () {
        //子复选框的事件   
        //当没有选中某个子复选框时，SelectAll取消选中  
        if (!$(this).is(":checked")) {
            $(".check-all").prop("checked", false);
            var id = $(this).val();
            $("input[name='buy_num["+id+"]']").attr('disabled',true);
        }else{
             var id = $(this).val();
            $("input[name='buy_num["+id+"]']").attr('disabled',false);
        }
        var chsub = $(".check-one").length; //获取subcheck的个数  
        var checkedsub = $(".check-one:checked").length; //获取选中的subcheck的个数  
        if (checkedsub == chsub) {
            $(".check-all").prop("checked", true);
        }
        changeInfo(0, 0);
    });
    //清空购物车
    $("#cartclear").on('click', function () {
        layer.confirm("确认清空购物车?", function () {
            $.post(CART.truncate_url, {}, function (data) {
                if (data['status'] == 'success') {
                    layer.msg('清空成功!');
                    $(".cartsimple").empty();
                    check_cart_empty();
                } else {
                    layer.msg('清空失败!');
                }
            }, "json");
        });

    });
    //删除选中
    $("#cartdel").on('click', function () {
        var selectids = [];
        $("input[name^='selectids']:checked").each(function (i) {
            selectids.push($(this).val());
        });
        if (selectids.length == 0) {
            layer.msg('请选择需要删除的商品!');
            return;
        }
        layer.confirm("确认删除选中的商品?", function () {
            $.post(CART.delete_multi_url, {ids: selectids}, function (data) {
                if (data['status'] == 'success') {
                    layer.msg('删除成功!');
                    $("input[name^='selectids']:checked").each(function (i) {
                        $(this).closest(".carttr").remove();
                    });
                    changeInfo(0, 0);
                    check_cart_empty();
                } else {
                    layer.msg('删除失败!');
                }
            }, "json");
        });

    });
    //增加或删除
    $(".btn-adddec").on("click", function () {
        var cell = $(this).closest(".carttr");
        var id = cell.attr("data-id");
        var buyinput = cell.find("input[name='buy_num[" + id + "]']");
        var num = buyinput.val();
        var text = $(this).data("act");
        if (text == '-') {
            if (num > 1) {
                num--;
            } else {
                num = 1;
            }
        } else {
            num++;
        }
        if (buyinput.val() != num)
            changeInfo(id, num);
        buyinput.val(num);

    });
    //数量变更
    $(".buy-num-bar input").on("change", function () {
        var num = parseInt($(this).val());
        var id = $(this).closest(".carttr").attr("data-id");
        changeInfo(id, num);
    });
    //删除单项
    $(".cart-del").on("click", function () {
        var _this = this;
        layer.confirm("确认将此商品从购物车中移除?", function () {
            $.post(CART.delete_url, {id: $(_this).closest(".carttr").attr("data-id")}, function (data) {
                if (data['status'] == 'success') {
                    layer.msg('移除成功!');
                    $(_this).closest(".carttr").remove();
                    changeInfo(0, 0);
                    check_cart_empty();
                } else {
                    layer.msg('删除失败!');
                }
            }, 'json');
        });
    });
    //移入收藏夹
    $(".movetoattention").on("click", function () {
        var _this = this;
        $.post(CART.movetoattention_url, {goods_id: $(this).closest(".carttr").attr("data-id")}, function (data) {
            if (data['status'] === 1) {
                layer.msg("移入收藏夹成功");
                //成功后从购物车中移除
                $.post(CART.delete_url, {id: $(_this).closest(".carttr").attr("data-id")}, function (data) {
                    if (data['status'] == 'success') {
                        $(_this).closest(".carttr").remove();
                        changeInfo(0, 0);
                        check_cart_empty();
                    } else {
                        layer.msg("操作失败");
                    }
                }, 'json');
            } else if (data['status'] == 2) {
                layer.msg("收藏夹已经有相同商品");
            } else {
                layer.msg("操作失败");
            }

        }, 'json');
    });

    //去结算
    $("#cart-order").on("click", function () {
        if ($("input[name^='selectids']:checked").size() <= 0) {
            layer.msg("未选中任何商品");
            return false;
        }
        $("#cart-form").submit();

    });
    //变更状态时
    $(document).on("change", "input[name^='selectids']", function () {
        changeInfo(0, 0);
    });

    function changeInfo(id, num) {
        $.post(CART.changeinfo_url, {id: id, num: num}, function (data) {

            var selectids = [];
            $("input[name^='selectids']:checked").each(function (i) {
                selectids.push($(this).val());
            });
            var total = 0.00;
            var weight = 0;
            var nums = 0;
            for (var i in data) {
                if ($.inArray(data[i]['id'], selectids) != -1) {
                    console.log(data[i]['id'], selectids);
                    total += parseFloat(data[i]['amount']);
                    weight += data[i]['weight'] * data[i]['num'];
                    nums += data[i]['num'];
                }
            }
            if (id > 0) {
                var cell = $(".carttr[data-id='" + id + "']");
                var buyinput = cell.find("input[name='buy_num[" + id + "]']");
                //$("#"+id).find(".amount").text(data[id]['amount']);
                cell.find(".prom").text(data[id]['prom']);
                var buyinputparent = buyinput.parent();
                if (parseInt(buyinput.val()) > parseInt(data[id]['store_nums'])) {
                    buyinput.val(data[id]['store_nums']);

                    if (buyinputparent.find(".msg-simple-error").size() == 0)
                        buyinputparent.append("<div class='msg-simple-error'>最多购买" + data[id]['store_nums'] + "件</div>");
                } else {
                    buyinput.val(data[id]['num']);
                    buyinputparent.find(".msg-simple-error").remove();
                }
            }
            $(".cart-weight").text((weight / 1000).toFixed(2));
            $(".cart-nums").text(nums);
            $(".cart-total").text(total.toFixed(2));
        }, "json");
    }

});

