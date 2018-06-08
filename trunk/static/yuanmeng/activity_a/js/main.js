;

/*解决某些低端Android手机缩放失真*/
var fixScreen = function () {
    var metaEl = doc.querySelector('meta[name="viewport"]'),
        metaCtt = metaEl ? metaEl.content : '',
        matchScale = metaCtt.match(/initial\-scale=([\d\.]+)/),
        matchWidth = metaCtt.match(/width=([^,\s]+)/);

    if (metaEl && !matchScale && (matchWidth && matchWidth[1] != 'device-width')) {
        var width = parseInt(matchWidth[1]),
            iw = win.innerWidth || width,
            ow = win.outerWidth || iw,
            sw = win.screen.width || iw,
            saw = win.screen.availWidth || iw,
            ih = win.innerHeight || width,
            oh = win.outerHeight || ih,
            ish = win.screen.height || ih,
            sah = win.screen.availHeight || ih,
            w = Math.min(iw, ow, sw, saw, ih, oh, ish, sah),
            scale = w / width;

        if (scale < 1) {
            metaEl.content += ',initial-scale=' + scale + ',maximum-scale=' + scale + ', minimum-scale=' + scale;
        }
    }
}