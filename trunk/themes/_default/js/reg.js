$(function () {
    //注册协议
    $("#user-license").on("click", function () {
        layer.open({
            id: 'license-dialog',
            title: '用户注册协议',
            content: $('#license-content').html(),
          
            area: ['600px', '400px'],
            scrollbar: false,
            btn: ['我同意', '不同意'],
            btn1: function (index) {
                console.log(index);
                $('#readme').prop("checked", 'true');
                autoValidate.showMsg({id: document.getElementById('readme'), error: false, msg: ''});
                layer.close(index);
            },
            btn2: function () {
                $('#readme').removeProp("checked");
                autoValidate.showMsg({id: document.getElementById('readme'), error: true, msg: '必须同意用户注册协议'});
            }
        });
    });
    //发送验证码
    $("#sendSMS").click(function () {
        var data = 'mobile=' + $("#mobile").val() + '&r=' + Math.random();
        if (autoValidate.validate(document.getElementById('mobile')) === false)
            return;
        $.ajax({
            type: "get",
            url: REG.send_sms_url,
            data: data,
            dataType: 'json',
            success: function (result) {
                if (result['status'] == 'success') {
                    $('#mobile').attr("readonly", "readonly");
                    var send_sms = $("#sendSMS");
                    send_sms.attr("disabled", true);
                    send_sms.addClass("btn-disable");
                    var i = 120;
                    send_sms.val(i + '秒后重新获取');
                    var timer = setInterval(function () {
                        i--;
                        send_sms.val(i + '秒后重新获取');
                        if (i <= 0) {
                            clearInterval(timer);
                            send_sms.val('获取短信验证码');
                            $('#mobile').removeAttr("readonly");
                            send_sms.removeClass("btn-disable");
                            send_sms.attr("disabled", false);
                        }
                    }, 1000);
                } else {
                    art.dialog.tips("<p class='fail'>" + result['msg'] + "</p>");
                }
            }
        });
    });
    $("#readme").on("change", function () {
        if ($("#readme:checked").length > 0)
            autoValidate.showMsg({id: document.getElementById('readme'), error: false, msg: ''});
        else
            autoValidate.showMsg({id: document.getElementById('readme'), error: true, msg: '同意后才可注册'});
    });
    function checkReadme(e) {
        if (e)
            return false;
        else {
            if ($("#readme:checked").length > 0)
                return true;
            {
                autoValidate.showMsg({id: document.getElementById('readme'), error: true, msg: '同意后才可注册'});
                return false;
            }
        }
    }

    $("input[pattern]").on("blur", function (event) {
        $(".invalid-msg , .valid-msg").hide();
        var current_input = $(this);
        var result = autoValidate.validate(event);
        if (result) {
            current_input.parent().removeClass('invalid').addClass('valid');
        } else {
            current_input.parent().removeClass('valid').addClass('invalid');
        }
        if (result) {

            var id = current_input.attr('id');
            var value = $(this).val();
            if (id == 'email' || id == 'mobile' || id == 'name') {
                current_input.next().remove();
                var urldict = {email: REG.ajax_email_chk_url, mobile: REG.ajax_mobile_chk_url, name: REG.ajax_name_chk_url};
                $.post(urldict[id] + value, function (data) {
                    var msg = '合法用户';
                    if (!data['status']) {
                        msg = data['msg'];
                        current_input.next().show();
                        current_input.parent().removeClass('valid').addClass('invalid');
                    } else {
                        current_input.parent().removeClass('invalid').addClass('valid');
                    }
                    autoValidate.showMsg({id: document.getElementById(id), error: !data['status'], msg: msg});
                }, 'json');
            }
            if (id == 'verifyCode') {
                $.post(REG.ajax_valid_code_url + value, function (data) {
                    autoValidate.showMsg({id: document.getElementById(id), error: !data['status'], msg: data['msg']});
                    if (!data['status'])
                        current_input.next().show();
                }, 'json');
            }
            $(".invalid-msg").show();
        } else {
            current_input.next().show();
        }
    });
    $(".regtype").on("click", function () {
        var type = $(this).data("type");
        $(".regtype h1").removeClass("registeredselect");
        $("h1", this).addClass("registeredselect");
        $("#mobile,#email").parent().hide();
        $("#" + type).parent().show();
        $("#t_mobilecode,#t_emailcode").hide();
        $("#t_" + type + "code").show();
        $("input[name=reg_type]").val(type);
        if (type == 'mobile') {
            $("#mobile").removeProp("disabled");
            $("#mobile").prop("pattern", "mobi");
            $("#email").prop("disabled", true);
            $("#email").next().remove();
            $("#email").parent().removeClass("invalid");
            $("#email").removeClass("invalid-text").prop("pattern", "");
            $("#mobile_code").removeProp("disabled");
            $("#mobile_code").prop("pattern", "\\d{6}");
            $("#verifyCode").prop("disabled", true);
            $("#verifyCode").parent().find(".invalid-msg").remove();
            $("#verifyCode").parent().removeClass("invalid");
            $("#verifyCode").removeClass("invalid-text").prop("pattern", "");
        } else {
            $("#email").removeProp("disabled");
            $("#email").prop("pattern", "email");
            $("#mobile").prop("disabled", true);
            $("#mobile").next().remove();
            $("#mobile").parent().removeClass("invalid");
            $("#mobile").removeClass("invalid-text").prop("pattern", "");
            $("#verifyCode").removeProp("disabled");
            $("#verifyCode").prop("pattern", "\\w{4}");
            $("#mobile_code").prop("disabled", true);
            $("#mobile_code").parent().find(".invalid-msg").remove();
            $("#mobile_code").parent().removeClass("invalid");
            $("#mobile_code").removeClass("invalid-text").prop("pattern", "");
        }

    });
    $(".regtype[data-type='" + REG.regtype + "']").trigger("click");
    if (REG.invalid) {
        var form = new Form();
        autoValidate.showMsg({id: $("input[name='" + REG.invalid.field + "']").get(0), error: true, msg: REG.invalid.msg});
    }
});