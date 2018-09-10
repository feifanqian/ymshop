
//更新库存信息
var store_nums = 0;
$(function () {

    $("input[name='select_address_id']").on("click", function () {
        $("#address_id").val($(this).val());
        $("#currentaddress").html($(this).closest(".weui_cell").find(".weui_cell_bd").html());
        $(".address-list li").removeClass("selected");
        $("input[name='address_id']").removeAttr("checked");
        $("input[name='address_id']", this).attr("checked", "checked");
        $(this).addClass("selected");
        $("a.default").hide();
        $("a.default", this).show();
        var address_id = $("input[name='address_id']").val();
        var weight = $("#fare").attr("data-weight");
        var productarr = {};
        $("#goods-list a[data-id]").each(function(){
            productarr[$(this).data("id")] = $(this).data("nums");
        });
        $.post(Order.calculatefareurl, {weight: weight, id: address_id, product:productarr}, function (data) {
            if (data['status'] == 'success') {
                $("#fare").text(data['fee']);
                calculate();
            }
        }, 'json');
        $(".close-popup").trigger("click");
    });
    $("input[name='select_address_id']:checked").trigger("click");
    $("#paytype").select({
        title: "选择支付方式",
        items: Order.paytypelist,
        onChange: function () {
            $("input[name='payment_id']").val($("#paytype").attr("data-values"));
            $("input[name='payment_id']").trigger("change");
            calculate();
        }
    });
    $("#voucher-n").Paging({
        url: Order.getvoucherurl,
        params: {amount: Order.total},
        callback: function () {
            calculate();
            $("#voucher-n input[name='voucher']").each(function () {
                $(this).on("click", function () {
                    calculate();
                });
            });
        }
    });
    $("#voucher-cancel").on("click", function () {
        if ($("#voucher-n input[name='voucher']:checked").size() > 0) {
            $("#voucher-n input[name='voucher']:checked").attr("checked", false);
            calculate();
        }
    })
    $("#voucher-btn").on("click", function () {
        $("#voucher-n").toggle();
        if ($("i", this).hasClass("icon-plus")) {
            $("i", this).removeClass("icon-plus");
            $("i", this).addClass("icon-minus");
        } else {
            $("i", this).removeClass("icon-minus");
            $("i", this).addClass("icon-plus");
        }
    });

    $("#prom_order").on("change", function () {
        calculate();
    });
    $("#is_invoice").on("click", function () {
        if (!!$(this).attr("checked")) {
            $("#invoice").show();
        } else{
            $("#invoice").hide();
        }
        calculate();
    })

    //计算实付金额
    function calculate() {
        var total = parseFloat($("#total-amount").attr("total"));
        var voucher = 0;
        var fare = parseFloat($("#fare").text());
        if ($("#voucher-n input[name='voucher']:checked").size() > 0) {
            voucher = parseFloat($("#voucher-n input[name='voucher']:checked").attr('data-value'));
            if (voucher == undefined)
                voucher = 0;
        }
        total -= voucher;
        $("#voucher").text(voucher.toFixed(2));
        if (total <= 0)
            total = 0;

        if ($("#is_invoice").size() > 0) {
            if (!!$("#is_invoice").attr("checked")) {
                var tax_fee = (total * Order.tax / 100);
                total += tax_fee;
                $(".taxes").text(tax_fee.toFixed(2));
            } else {
                $(".taxes").text("0.00");
            }
        }

        total += fare;
        if ($("#prom_order").size() > 0) {
            var prom_order = $("#prom_order").find("option:selected");
            var type = prom_order.attr("data-type");
            if($("input[name='pay_type']").val()=='huabipay'){
                var value =0.00;
                $("#prom_order_text").text("0.00");
            }else{
                var value = parseFloat(prom_order.attr("data-value"));
            }
            var data_point = parseInt($("#point").attr("data-point"));
             
            $("#point").text(data_point);
            if (type != 4) {

                if (type == 2) {
                    data_point = data_point * value;
                    $("#point").text(data_point);
                    $("#prom_order_text").text('0.00');
                } else {
                    total = (total - value);
                    $("#prom_order_text").text(value.toFixed(2));
                }

            } else {
                total = (total - value - fare);
                $("#prom_order_text").text(fare.toFixed(2));
            }
        }
        var oldtotal = $("#real-total").text();
        
        $("#real-total").text(total.toFixed(2));
        if($("#still_pay").length>0){
            changeStillPay(oldtotal,total);
        }
    }
    //calculate();

    //获取共享地址
    function editAddress() {
        WeixinJSBridge.invoke(
                'editAddress',
                Order.wechataddress,
                function (res) {
                    if (res.err_msg == "edit_address:ok") {
                        $.ajax({
                            url: "/ucenter/address_wechat",
                            type: 'POST',
                            data: res,
                            dataType: 'json',
                            success: function (ret) {
                                if (ret.code == 0) {
                                    location.reload();
                                }else{
                                    $.toast("获取共享地址失败",'forbidden');
                                }
                            }
                        });
                    }
                }
        );
    }

    window.onload = function () {
        if (typeof WeixinJSBridge == "undefined") {
            if (document.addEventListener) {
                document.addEventListener('WeixinJSBridgeReady', editAddress, false);
            } else if (document.attachEvent) {
                document.attachEvent('WeixinJSBridgeReady', editAddress);
                document.attachEvent('onWeixinJSBridgeReady', editAddress);
            }
        } else {
            editAddress();
        }
    }

});