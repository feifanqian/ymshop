

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
        if (!!$("#is_invoice").prop("checked")) {
            var tax_fee = ((total * Order.tax) / 100);
            total += tax_fee;
            $("#taxes").text(tax_fee.toFixed(2));
        } else {
            $("#taxes").text("0.00");
        }
    }

    total += fare;
    if ($("#prom_order").size() > 0) {
        var prom_order = $("#prom_order").find("option:selected");
        var type = prom_order.attr("data-type");
        if($("#huabi_pay_radio").is(":checked")){
          $("#prom_order_text").text('0.00');
           var value = 0.00;
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
    
    if (total < 0)
        total = 0;
    var old_total = parseFloat($("#real-total").text());
    var old_still = parseFloat($("#still-pay-amount").text());
    $("#real-total").text(total.toFixed(2));
    if(total>old_total){
        $("#still-pay-amount").text((old_still+total-old_total).toFixed(2));
    }else{
        $("#still-pay-amount").text((old_still+total-old_total).toFixed(2));
    }
}

$(function () {

    $(".address-list .modify").on("click", function () {
        var id = $(this).attr("data-value");
        art.dialog.open('{url:/simple/address_other/id/}' + id, {width: 960, height: 460, lock: true});
        return false;
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
            $("#voucher-n input[name='voucher']:checked").prop("checked", false);
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
    })

    $(".address-list li").each(function () {
        $(this).has("input[name='address_id']:checked").addClass("selected");
        $(this).on("click", function () {
            $(".address-list li").removeClass("selected");
            $("input[name='address_id']").removeProp("checked");
            $("input[name='address_id']", this).prop("checked", "checked");
            $(this).addClass("selected");
            $("a.default").hide();
            $("a.default", this).show();
            var id = $("input[name='address_id']", this).val();
            var weight = $("#fare").attr("data-weight");
            var productarr = {};
            $("#goods-list tr[data-id]").each(function(){
                productarr[$(this).data("id")] = $(this).data("nums");
            });
            $.post(Order.calculatefareurl, {weight: weight, id: id, product:productarr}, function (data) {
                if (data['status'] == 'success') {
                    $("#fare").text(data['fee']);
                    calculate();
                }
            }, 'json');
        });
    });
    FireEvent($(".address-list input[name='address_id']:checked").get(0), "click");
    
    $(".payment-list li").each(function () {
        $(this).has("input[name='payment_id']:checked").addClass("selected");
        $(this).on("click", function () {
            $(".payment-list li").removeClass("selected");
            $("input[name='payment_id']").removeProp("checked");
            $("input[name='payment_id']", this).prop("checked", "checked");
            $("input[name='payment_id']").trigger("change");
            $(this).addClass("selected");
            calculate();
        });
    });
    $("#prom_order").on("change", function () {
        calculate();
    });
    $("#is_invoice").on("click", function () {
        if (!!$(this).prop("checked")) {
            $("#invoice").show();
        } else
            $("#invoice").hide();
        calculate();
    });
    calculate();
});