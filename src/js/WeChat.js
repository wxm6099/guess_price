/**
 * Created by sense on 15/12/7.
 */

var currUrl = window.location.href.replace(window.location.hash,'');
$.getJSON('http://mielseno.com/wx/signature.php?url=' + encodeURIComponent(currUrl)).done(function(data) {
    wx.config({
        debug: false,
        appId: data.appId,
        timestamp: data.timestamp,
        nonceStr: data.nonceStr,
        signature: data.signature,
        jsApiList: [
            'onMenuShareTimeline',
            'onMenuShareAppMessage'
        ]
    });

    wx.ready(function () {
        var shareTitle = '组团猜价格，裤裤送到家~';
        var shareDesc = '组团作战胜算更大\n超好穿的小裤裤等着我们呐!';
        var shareImg = 'http://mielseno.com/guessprice/images/icon_share.png';

        // 分享给朋友事件绑定
        wx.onMenuShareAppMessage({
            title: shareTitle,
            desc: shareDesc,
            imgUrl: shareImg
        });

        // 分享到朋友圈
        wx.onMenuShareTimeline({
            title: shareTitle,
            imgUrl: shareImg
        });
    });
});
