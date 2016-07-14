// Comments switcher
function showVK(Tshow, Thide) {
    if (!Tshow && Tshow != 0) Tshow = 1000;
    if (!Thide && Thide != 0) Thide = 1500;
    jQuery("#vkapi").show(Tshow);
    jQuery(".fb-comments").hide(Thide);
    jQuery("#comments").hide(Thide);
    jQuery("#respond").hide(Thide);
}
function showFB(Tshow, Thide) {
    if (!Tshow && Tshow != 0) Tshow = 1000;
    if (!Thide && Thide != 0) Thide = 1500;
    jQuery(".fb-comments").show(Tshow);
    jQuery("#vkapi").hide(Thide);
    jQuery("#comments").hide(Thide);
    jQuery("#respond").hide(Thide);
}
function showWP(Tshow, Thide) {
    if (!Tshow && Tshow != 0) Tshow = 1000;
    if (!Thide && Thide != 0) Thide = 1500;
    jQuery("#comments").show(Tshow);
    jQuery("#respond").show(Tshow);
    jQuery("#vkapi").hide(Thide);
    jQuery(".fb-comments").hide(Thide);
}

// SignOn
function onSignon(response) {
    if (response.session) {
        VK.Api.call(
            'getProfiles',
            {
                'v': '2.0',
                'uids': response.session.mid,
                'fields': 'uid,first_name,nickname,last_name,screen_name,photo_medium_rec'
            },
            function (response) {

                var parts = window.location.search.substr(1).split("&");
                var $_GET = {};
                for (var i = 0; i < parts.length; i++) {
                    var temp = parts[i].split("=");
                    $_GET[decodeURIComponent(temp[0])] = decodeURIComponent(temp[1]);
                }
                jQuery.post(vkapi.wpurl + '/wp-content/plugins/vkontakte-api/php/connect.php', response.response[0], function (text) {
                    if (jQuery.trim(text) == 'Ok') {
                        jQuery("div.vkapi_vk_login").html("<span style='color:green'>Result: ✔ " + text + "</span>");
                        if (typeof $_GET['redirect_to'] != 'undefined') {
                            document.location.href = $_GET['redirect_to'];
                        } else if ($_GET['loggedout'] == 'true') {
                            document.location.href = '/';
                        } else {
                            document.location.reload();
                        }
                    } else {
                        jQuery("div.vkapi_vk_login").html('<span style="color:red">Result: ' + text + '</span>');
                    }
                });
            }
        );
    } else {
        VK.Auth.login(onSignon);
    }
}

// Mail callback + count plus
function vkapi_comm_plus(id, num, last_comment, date, sign) {
    var data = {
        social: 'vk',
        id: id,
        num: num,
        last_comment: last_comment,
        date: date,
        sign: sign
    };
    var jqxhr = jQuery.post(vkapi.wpurl + '/wp-content/plugins/vkontakte-api/php/mail.php', data);
    jqxhr.fail(function () {
        setTimeout(vkapi_comm_plus(id, num, last_comment, date, sign), 5000);
    });
}

function fbapi_comm_plus(id) {
    var data = {
        social: 'fb',
        id: id
    };
    // @var vkapi Object
    var jqxhr = jQuery.post(vkapi.wpurl + '/wp-content/plugins/vkontakte-api/php/mail.php', data);
    jqxhr.fail(function () {
        setTimeout(fbapi_comm_plus(id), 5000);
    });
}

// Count minus
function vkapi_comm_minus(id, num, last_comment, date, sign) {
    onChangeRecalc(num);
    var data = {
        social: 'vk',
        id: id,
        num: num,
        last_comment: last_comment,
        date: date,
        sign: sign
    };
    var jqxhr = jQuery.post(vkapi.wpurl + '/wp-content/plugins/vkontakte-api/php/count.php', data);
    jqxhr.fail(function () {
        setTimeout(vkapi_comm_minus(id, num, last_comment, date, sign), 5000);
    });
}

function fbapi_comm_minus(id) {
    var data = {
        social: 'fb',
        id: id
    };
    var jqxhr = jQuery.post(vkapi.wpurl + '/wp-content/plugins/vkontakte-api/php/count.php', data);
    jqxhr.fail(function () {
        setTimeout(fbapi_comm_minus(id), 5000);
    });
}

// Comments padding
jQuery(function () {
    jQuery("#comments-title").css("padding", "0px 0px");
});

// On VK add comment
function onChangePlusVK(num, last_comment, date, sign) {
    var id = jQuery("#vkapi_wrapper").attr("data-vkapi-notify");
    vkapi_comm_plus(id, num, last_comment, date, sign);
    onChange(num, last_comment, date, sign);
    onChangeRecalc(num);
}
// On VK del comment
function onChangeMinusVK(num, last_comment, datee, sign) {
    var id = jQuery("#vkapi_wrapper").attr("data-vkapi-notify");
    vkapi_comm_minus(id, num, last_comment, datee, sign);
}

// On FB add comment
function onChangePlusFB(array) {
    var id = jQuery("#vkapi_wrapper").attr("data-vkapi-notify");
    fbapi_comm_plus(id);
}
// On FB del comment
function onChangeMinusFB(array) {
    var id = jQuery("#vkapi_wrapper").attr("data-vkapi-notify");
    fbapi_comm_minus(id);
}

// Decode like php
function html_entity_decode(str) {
    var text_area = document.createElement('textarea');
    text_area.innerHTML = str;
    return text_area.value;
}
