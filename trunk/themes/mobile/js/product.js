
//更新库存信息
var store_nums = 0;
$(function () {
    $("#start").cityPicker({
        title: "请选择收货地址"
    });
    $("#swiper-product").swiper({autoplay: 3000, pagination: '.swiper-pagination', loop: true});
    $(".weui_tab_bd_item").css("min-height", (document.documentElement.clientHeight - 110 + "px"));
    $(".spec-values .attr-item a").on("click", function () {
        var disabled = $(this).hasClass('disabled');
        if (disabled)
            return;
        var flage = $(this).hasClass('selected');
        $(this).parent().find("a").removeClass("selected");
        if (!flage) {
            $(this).addClass("selected");
        }
        changeStatus();
        if ($(".spec-values").length == $(".spec-values .selected").length) {
            var sku = new Array();
            $(".spec-values .selected").each(function (i) {
                sku[i] = $(this).attr("data-value");
            });
            var sku_key = ";" + sku.join(";") + ";";
            if (Product.skuMap[sku_key] != undefined) {
                var sku = Product.skuMap[sku_key];
                $("#sell_price,.price em").text(sku['sell_price']);
                $("#store_nums").text(sku['store_nums']);
                $("#goods_nums").text(sku['store_nums']);
                $("#buy-num").val(Math.min(1, parseInt(sku['store_nums'])));
                if ($("#prom_price").size() > 0) {
                    var formula = $("#prom_price").attr('formula');
                    var prom_price = eval(sku['sell_price'] + formula);
                    if (prom_price <= 0)
                        prom_price = 0;
                    $("#prom_price").text(Product.currency + " " + prom_price.toFixed(2));
                }

                $("#market-price").text(sku['market_price']);
                $("#pro-no").text(sku['pro_no']);
            }
        }
    });

    $("#buy-num-bar a:eq(0)").on("click", function () {
        var num = $("#buy-num-bar input").val();
        var max = parseInt($("#store_nums").text());
        if (num > 1)
            num--;
        else
            num = Math.min(1, max);
        $("#buy-num-bar input").val(num);
    });
    $("#buy-num-bar a:eq(1)").on("click", function () {
        var num = $("#buy-num-bar input").val();
        var max = parseInt($("#store_nums").text());
        if (num < max)
            num++;
        else
            num = max;
        $("#buy-num-bar input").val(num);
    });
    $("#buy-num-bar input").on("change", function () {
        var value = $(this).val();
        var max = parseInt($("#store_nums").text());
        if ((/^\d+$/i).test(value)) {
            value = Math.abs(parseInt(value));
            if (value < 1)
                value = 1;
            if (value > max)
                value = max;
        } else {
            value = 1;
        }
        $(this).val(value);
    });
    //点击购买
    $(".buy-now").on("click", function (e) {
    // console.log(123);return false;
        var hasopen = $("#selectid").css("display") == "block" ? true : false;
        if (hasopen) {
            var hasattr = $(".spec-values").length > 0 ? true : false;
            var hascheck = $(".spec-values").length == $(".spec-values .selected").length ? true : false;
            if (hascheck) {
                var product = currentProduct();
                var num = parseInt($("#buy-num").val());
                var type = $(this).attr("data-type");
                if (type == "groupbuy") {
                    var target = $(this).attr("data-target");
                    var url = Product.groupBuyUrl + product['id']+'/target/'+target;
                    location.href = url;
                } else if (type == "flashbuy") {
                    var url = Product.flashBuyUrl + product['id'];
                    location.href = url;
                }else if(type == "pointbuy"){
                    var url = Product.pointBuyUrl + product['id'];
                    location.href = url;
                }else if(type == "pointflash"){
                    var url = Product.pointflashUrl + product['id'];
                    location.href = url;
                }else if(type == "pointwei"){
                    var url = Product.pointWeiUrl + product['id'];
                    location.href = url;
                }else {
                    $.post(Product.addGoodsCartUrl, {id: product['id'], num: num}, function (data) {
                        if (data.length < 1) {
                            showMsgBar('stop', "库存不足！");
                        } else {
                            location.href = Product.goodsOrderUrl;
                        }
                    }, "json");
                }
            } else {
                $.toast("请选择商品属性", "text");
            }
        } else {
            //$(".btn-bar").clone(true).appendTo($("#selectid .weui-popup-modal"));
            $("#selectid").popup();
        }
        return false;
    });

    //添加到购物车
    $(".add-cart").on("click", function () {
        var hasopen = $("#selectid").css("display") == "block" ? true : false;
        if (hasopen) {
            var product = currentProduct();
            if (product) {
                var pid = product["id"];
                var num = parseInt($("#buy-num").val());
                var max = parseInt($("#store_nums").text());
                var cart_num = parseInt($("#" + pid).find(".num").text());
                if ((num + cart_num) > max) {
                    showMsgBar('stop', "连同购物车里的商品数量，超出了允许购买的最大量！");
                    return false;
                } else if (max <= 0) {
                    showMsgBar('stop', "库存不足！");
                    return false;
                } else {

                }
                $.post(Product.addCartUrl, {id: pid, num: num}, function (data) {
                    updateCart(data);
                }, "json");
            } else {
                showMsgBar('alert', "请选择您要购买的商品规格！");
            }
        } else {
            //$(".btn-bar").clone(true).appendTo($("#selectid .weui-popup-modal"));
            $("#selectid").popup();
        }
    });

    $("#attention").on("click", function () {
        $.post(Product.attentionUrl, {goods_id: Product.id}, function (data) {
            if (data['status'] == 2)
                art.dialog.tips("<p class='warning'>已关注过了该商品！</p>");
            else if (data['status'] == 1)
                art.dialog.tips("<p class='success'>成功关注了该商品!</p>");
            else
                art.dialog.tips("<p class='warning'>你还没有登录！</p>");
        }, 'json')
    });
});
//取得当前商品
function currentProduct() {
    if ($(".spec-values").length == 0) {
        return Product.skuMap[''];
    }
    if ($(".spec-values").length == $(".spec-values .selected").not(".disabled").length) {
        var sku = new Array();
        $(".spec-values .selected").each(function (i) {
            sku[i] = $(this).attr("data-value");
        });
        var sku_key = ";" + sku.join(";") + ";";
        if (Product.skuMap[sku_key] != undefined) {
            return Product.skuMap[sku_key];
        } else
            return null;
    } else
        return null;
}

function updateCart(data) {
    var num = 0;
    for (var i in data)
        num++;
    $(".cart-num").text(num);
    $.modal({
        title: "温馨提示",
        text: "商品已添加到购物车！",
        buttons: [
            {
                text: "去结算", onClick: function () {
                    window.location = Product.cartUrl;
                }
            },
            {
                text: "再逛会", className: "default", onClick: function () {

                }
            },
        ]
    });
}

function showMsgBar(type, text) {
    $.toast(text, "text");
}

function changeStatus() {
    var specs_array = new Array();
    var specs_text = new Array();
    $(".spec-values").each(function (i) {
        var selected = $(this).find(".selected");
        if (selected.length > 0) {
            specs_array[i] = selected.attr("data-value");
            specs_text.push(selected.text());
        } else {
            specs_array[i] = "\\\d+:\\\d+";
        }
    });
    $("#attr-text").html(specs_text.join(" / "));
    $("#choiceattr").html(specs_text.join(" / "));
    $(".spec-values").each(function (i) {
        var selected = $(this).find(".selected");
        $(this).find("li").removeClass("disabled");
        var k = i;
        $(this).find("li").each(function () {

            var temp = specs_array.slice();
            temp[k] = $(this).attr('data-value');
            var flage = false;
            for (sku in Product.skuMap) {
                var reg = new RegExp(';' + temp.join(";") + ';');
                if (reg.test(sku) && Product.skuMap[sku]['store_nums'] > 0)
                    flage = true;
            }
            if (!flage)
                $(this).addClass("disabled");
        })

    });
}