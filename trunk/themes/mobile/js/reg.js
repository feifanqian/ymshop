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
            btn1:function(index){
                $('#readme').prop("checked", 'true');
                autoValidate.showMsg({id: document.getElementById('readme'), error: false, msg: ''});
                layer.close(index);
            },
            btn2:function(){
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
    $("input[name='email']").on("change", function (event) {
        if (autoValidate.validate(event)) {
            $.post(REG.ajax_email_chk_url + $(this).val(), function (data) {
                autoValidate.showMsg({id: document.getElementById('email'), error: !data['status'], msg: data['msg']});
            }, 'json');
        }
    });
    $("input[name='mobile']").on("change", function (event) {
        if (autoValidate.validate(event)) {
            $.post(REG.ajax_mobile_chk_url + $(this).val(), function (data) {
                autoValidate.showMsg({id: document.getElementById('mobile'), error: !data['status'], msg: data['msg']});
            }, 'json');
        }
    });
    $("input[name='verifyCode']").on("change", function () {
        $.post(REG.ajax_email_valide_code_url + $(this).val(), function (data) {
            autoValidate.showMsg({id: document.getElementById('verifyCode'), error: !data['status'], msg: data['msg']});
        }, 'json');
    })
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
            if (current_input.attr('id') == 'email') {
                $.post(REG.ajax_email_chk_url + $(this).val(), function (data) {
                    var msg = '合法用户';
                    if (!data['status']) {
                        msg = '用户已存在';
                        current_input.next().show();
                        current_input.parent().removeClass('valid').addClass('invalid');
                    } else {
                        current_input.parent().removeClass('invalid').addClass('valid');
                    }
                    autoValidate.showMsg({id: document.getElementById('email'), error: !data['status'], msg: msg});
                }, 'json');
            }
            if (current_input.attr('id') == 'mobile') {
                $.post(REG.ajax_mobile_chk_url + $(this).val(), function (data) {
                    var msg = '合法用户';
                    if (!data['status']) {
                        msg = '用户已存在';
                        current_input.next().show();
                        current_input.parent().removeClass('valid').addClass('invalid');
                    } else {
                        current_input.parent().removeClass('invalid').addClass('valid');
                    }
                    autoValidate.showMsg({id: document.getElementById('mobile'), error: !data['status'], msg: msg});
                }, 'json');
            } else if (current_input.attr('id') == 'verifyCode') {
                $.post(REG.ajax_valid_code_url + $(this).val(), function (data) {
                    autoValidate.showMsg({id: document.getElementById('verifyCode'), error: !data['status'], msg: data['msg']});
                    if (!data['status'])
                        current_input.next().show();
                }, 'json');
            }
            $(".invalid-msg").show();
        } else {
            current_input.next().show();
        }
    });
});