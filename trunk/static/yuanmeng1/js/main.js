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

/**
 * 初始化轮播
 * @param el 操作对象
 * @param slidesPerView 默认显示几个item
 * @param spaceBetween item之间间距
 * @param slidesOffset 轮播整体两边的边距
 * @param navigation 左右箭头样式
 * @return {Swiper} Swiper对象
 */
function initSwiper(el, slidesPerView, spaceBetween, slidesOffset) {
    return new Swiper(el, {
        autoplay: true,
        slidesPerView: slidesPerView,
        spaceBetween: spaceBetween,
        slidesOffsetAfter: slidesOffset,
        slidesOffsetBefore: slidesOffset,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        }
    })
}