
/* global constant */
var ACCESS_PARAM = "access";
var WX_ACCESS_ENUM = {
    NO_ACCESS:0,
    ACCESS: 1
};
var GROUP_ID_PARAM = "gid";
var NO_GROUP_ID = 0;
var CODE_PARAM = "code";
var STATE_PARAM = "state";
var HOME_PARAM = "h";

var AJAX_TIMEOUT = 60000;

var GUESS_PRICE_SHOW_DEFAULT = "price_default";
var REQUEST_ERROR_TIP = "出错了，请重试。";
var REQUEST_ERROR_REOPEN_TIP = "出错了，请重新打开当前链接。";

var MIN_PRICE = 8.8;
var MAX_PRICE = 12.8;

var SET_WC_CODE_URL = "http://mielseno.com/GuessPrice/set_wx_code";
var LOGIN_URL = "http://mielseno.com/GuessPrice/login"
var SET_NEW_GROUP_URL = "http://mielseno.com/GuessPrice/set_new_group";
var JOIN_GROUP_URL = "http://mielseno.com/GuessPrice/join_group";
var SET_EXPRESS_INFO_URL = "http://mielseno.com/GuessPrice/set_express_info";
var GET_WINNER_LIST_URL = "http://mielseno.com/GuessPrice/get_winner_list";

/* global variables */
var gGuessPrice;
var gFromPricePage;


/* js entry */
function main(fromPricePage)
{
    gFromPricePage = fromPricePage;

    if (!fromPricePage && sessionStorage.isLogin && 1 == sessionStorage.isLogin)
    {
        // alert("already login.");
        return;
    }

    if (localStorage.openId && 0 < localStorage.openId.length)
    {
        // already access, request user info
        login(localStorage.openId);
    }
    else
    {
        var isHome = getIntParam(HOME_PARAM, 0);
        // not access
        if (isHome || (sessionStorage.startAccess && 1 == sessionStorage.startAccess))
        {
            setWxCode();
        }
    }
}

function checkAccess()
{
    var isHome = getIntParam(HOME_PARAM, 0);

    if (0 == isHome && (!sessionStorage.startAccess || 1 != sessionStorage.startAccess)
                    && (!localStorage.openId || 0 == localStorage.openId.length))
    {
        goToWxAccessPage();
    }
}

function goToWxAccessPage()
{
    location.href = makeWxAccessPage(location.href);
    sessionStorage.startAccess = 1;
}

function makeWxAccessPage(redirectUri)
{
    var wxAccessUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wxbb5f8f68dfc90d6c&redirect_uri=" + encodeURIComponent(redirectUri) + "&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect";
    return wxAccessUrl;
}

/*
function addWxAccessParam(url)
{
    return updateQueryStringParameter(url, ACCESS_PARAM, WX_ACCESS_ENUM.ACCESS);
}
*/

// 1001 
function setWxCode()
{
    var wxCode = getStringParam("code", "");

    if ("" == wxCode)
    {
        // TODO:
        alert("微信授权登陆出错。");
        return;
    }

    var groupId = getIntParam(GROUP_ID_PARAM, NO_GROUP_ID);
    var requestUrl = SET_WC_CODE_URL + "/" + wxCode + "/" + groupId;
    // TODO: log
    // alert("setWxCode requestUrl: " + requestUrl);
    sendRequestByGet(requestUrl, setWxCodeResp, setWxCodeErrorResp);
}

function login(openId)
{
    var groupId = getIntParam(GROUP_ID_PARAM, NO_GROUP_ID);
    var requestUrl = LOGIN_URL + "/" + encodeURIComponent(openId) + "/" + groupId;
    // TODO: log
    // alert("login requestUrl: " + requestUrl);
    sendRequestByGet(requestUrl, setWxCodeResp, setWxCodeErrorResp);
}

// 1002
function setWxCodeResp(responseData)
{
    // TODO: log    
    // alert("setWxCodeResp");
    if (responseData["uid"] && 0 == responseData["uid"])
    {
        alert(REQUEST_ERROR_REOPEN_TIP);
        return;
    } 

    sessionStorage.gUserInfo = JSON.stringify(responseData);
    // write localStorage
    localStorage.openId = responseData["uid"];
    // write sessionStorage
    sessionStorage.isLogin = 1;

    // game over
    if (1 == responseData["is_game_over"])
    {
        location.href = "./ending.html";
        return;
    }

    // TODO: log
    // printResponse(responseData);

    if (gFromPricePage)
    {
        showUserGroup();
    }
}

function showUserGroup()
{
    var userInfo = JSON.parse(sessionStorage.gUserInfo);

    if (!userInfo || 0 == userInfo["gid"])
    {
        // TODO:
        return;
    }

    if (userInfo["m"] && 0 < userInfo["m"].length) 
    {
        var countExceptLeader = 0;
        var index = 0;

        for (var i = 0; i < userInfo["m"].length; ++i)
        {
            if (0 == userInfo["m"][i]["l"])
            {
                countExceptLeader ++;
                index = countExceptLeader + 1;
            }
            else if (1 == userInfo["m"][i]["l"])
            {
                index = 1;
            }

            updateMemberView(userInfo["m"][i], index);
        }

        if (2 == userInfo["m"].length)
        {
            document.getElementById("item3_img").src = "images/头像占位3.png"; 
        }
    }
    else
    {
        gGuessPrice = GUESS_PRICE_SHOW_DEFAULT;
        var userInfo = userToMember(1);
        updateMemberView(userInfo, 1);
    }
    
    var newUrl = location.href;
    // delete code & state parameter
    newUrl = removeURLParameter(newUrl, CODE_PARAM);
    newUrl = removeURLParameter(newUrl, STATE_PARAM);
    newUrl = removeURLParameter(newUrl, GROUP_ID_PARAM);
    newUrl = removeURLParameter(newUrl, HOME_PARAM);

    // add "gid=<group_id>" parameter to current url.
    newUrl = updateQueryStringParameter(newUrl, GROUP_ID_PARAM, userInfo["gid"]);
    newUrl = updateQueryStringParameter(newUrl, HOME_PARAM, 0);
    // TODO: 不能跨域设置
    // var shareUrl = makeWxAccessPage(newUrl);
    /* 该方法在微信里“复制链接”不起作用，“分享到朋友圈”起作用 */
    window.history.replaceState(userInfo.html, userInfo.title, newUrl);
    // TODO: log
    // alert("share url: " + newUrl);
}

function setWxCodeErrorResp()
{
    alert(REQUEST_ERROR_REOPEN_TIP);
}

function updateMemberView(memberInfo, index)
{
    var memberImg = document.getElementById("item" + index + "_img");

    if (!memberInfo["h"] || 0 == memberInfo["h"].length)
    {
        memberImg.src = "images/无头像.png";
    }
    else
    {
        memberImg.src = memberInfo["h"];
    }

    var priceLabel = document.getElementById("item" + index + "_price");
     
    if (GUESS_PRICE_SHOW_DEFAULT == memberInfo["p"])
    {
        priceLabel.innerHTML = "";
    }
    else
    {
        priceLabel.innerHTML = memberInfo["p"] + "元";
    }
}

// 1003
function setNewGroup(openId, groupId, guessPrice)
{
    // TODO: log
    // alert(openId + "\n" + groupId + "\n" + guessPrice);

    // TODO: is float
    /*
    if (!isNaN(guessPrice)) 
    {
        // TODO: alert
        alert("请输入价格。");
        return;
    }
    */

    var requestUrl = SET_NEW_GROUP_URL + "/" + encodeURIComponent(openId) + "/" + groupId + "/" + guessPrice;
    // TODO: log
    // alert("requestUrl: " + requestUrl);
    sendRequestByGet(requestUrl, setNewGroupResp, setNewGroupErrorResp);
}

// 1004
function setNewGroupResp(responseData)
{
    // TODO: log
    // printResponse(responseData);

    if (1 != responseData["r"] || 0 != responseData["rea"])
    {
        if (1 == responseData["rea"])
        {
            alert("活动结束。");
            location.href = "./ending.html";
            return;
        }
        else
        {
            alert("开团出错。");
        }

        return;
    }

    // TODO: log
    // alert(sessionStorage.gUserInfo);

    // update member
    var userInfo = JSON.parse(sessionStorage.gUserInfo);
    if (!userInfo["m"])
    {
        userInfo["m"] = [];
    }

    userInfo["m"].push(userToMember(1));
    // TODO: log
    // printResponse(userInfo["m"]);

    setWxCodeResp(userInfo);
    showPopup(640,624, true);
}

function setNewGroupErrorResp()
{
    alert(REQUEST_ERROR_TIP);
}

// 1007
function joinGroup(openId, groupId, guessPrice)
{
    // alert(openId + "\n" + groupId + "\n" + guessPrice);
    var requestUrl = JOIN_GROUP_URL + "/" + encodeURIComponent(openId) + "/" + groupId + "/" + guessPrice;
    // alert("requestUrl: " + requestUrl);
    sendRequestByGet(requestUrl, joinGroupResp, joinGroupErrorResp);
}

// 1008
function joinGroupResp(responseData)
{
    // TODO: log
    // alert("joinGroupResp");
    // printResponse(responseData);

    if (1 != responseData["r"] || 0 != responseData["rea"])
    {
        if (2 == responseData["rea"])
        {
            alert("本团已经满员。");
        }
        else if (3 == responseData["rea"])
        {
            alert("活动结束。");
            location.href = "./ending.html";
            return;
        }
        else if (4 == responseData["rea"])
        {
            alert("参团次数已经用完。");
            return;
        }
        else
        {
            alert("参团出错。");
        }

        // refresh current page
        location.href = location.href;

        return;
    }

    // update member
    var userInfo = JSON.parse(sessionStorage.gUserInfo);
    if (!userInfo["m"])
    {
        userInfo["m"] = [];
    }
      
    userInfo["m"].push(userToMember(0));
    // TODO: log
    // printResponse(userInfo["m"]);

    setWxCodeResp(userInfo);

    if (1 == responseData["w"]){
        location.href = "win.html";
    }else{
        showPopup(640, 624, false);
    }

}

function userToMember(isLeader)
{
    var userInfo = {};
    var useInfoOrig = JSON.parse(sessionStorage.gUserInfo);

    userInfo["uid"] = useInfoOrig["uid"];
    userInfo["h"] = useInfoOrig["h"];
    userInfo["l"] = isLeader;
    userInfo["n"] = useInfoOrig["n"];
    userInfo["p"] = gGuessPrice;
    userInfo["s"] = useInfoOrig["s"];
 
    return userInfo;
} 

/* 
 * 0: not member
 * 1: leader
 * 2: common member
 */

function isMember()
{
    var userInfo = JSON.parse(sessionStorage.gUserInfo);

    if (userInfo && userInfo["m"] && 0 < userInfo["m"].length)
    {
        for (var i = 0; i < userInfo["m"].length; ++i)
        {
            if (userInfo["uid"] == userInfo["m"][i]["uid"])
            {
                if (1 == userInfo["m"][i]["l"])
                {
                    return 1;
                }
                else
                {
                    return 2;
                }
            }
        }
    }

    return 0;
}

function joinGroupErrorResp()
{
    alert(REQUEST_ERROR_TIP);
}

// 1011
function setExpressInfo(openId, groupId, name, address, phone)
{
    // TODO: check input parameter

    var requestUrl = SET_EXPRESS_INFO_URL + "/" + encodeURIComponent(openId) + "/" + groupId;
    var data = "n=" + encodeURIComponent(name) + "&a=" + encodeURIComponent(address) + "&p=" + encodeURIComponent(phone);

    // TODO: log
    // alert("requestUrl: " + requestUrl + "\ndata: " + data);
    sendRequestByPost(requestUrl, data, setExpressInfoResp, setExpressInfoErrorResp);
}

// 1012
function setExpressInfoResp(responseData)
{
    // TODO: log
    // printResponse(responseData);

    if (1 != responseData["r"] || 0 != responseData["rea"])
    {
        alert("出错了，请重新提交。");
        return;
    }   
    else
    {
        alert("成功提交收货地址。");
        hidePopupp();
    }
}

function setExpressInfoErrorResp()
{
    alert(REQUEST_ERROR_TIP);
}

// 1013
function getWinnerList(openId, groupId, startId, count, callback)
{
    // TODO: check input parameter

    requestUrl = GET_WINNER_LIST_URL + "/" + encodeURIComponent(openId) + "/" + groupId + "/" + startId + "/" + count ;    
    // TODO: log
    // alert("requestUrl: " + requestUrl);
    sendRequestByGet(requestUrl, callback, getWinnerListErrorResp);
}

// 1014
function getWinnerListResp(responseData)
{
    // TODO: log
    printResponse(responseData);

}

function getWinnerListErrorResp()
{
    alert(REQUEST_ERROR_TIP);
}

function guessPriceBtnOnClick()
{
    var memberType = isMember();

    if (memberType)
    {
        // alert("您已经猜过价格了。");
        if (1 == memberType)
        {
            showPopup(640, 624, true);
        }
        else
        {
            showPopup(640, 624, false);
        }

        return;
    }

    var guessPrice = getGuessPriceInput();

    if (0 == guessPrice.length)
    {
        alert("请输入价格。");
        return;
    }
    else if (!isValidPrice(guessPrice))
    {
        alert("请输入一个 8.8～12.8 之间的价格。");
        return;
    }

    var userInfo = JSON.parse(sessionStorage.gUserInfo);

    if (userInfo && userInfo["m"] && 0 < userInfo["m"].length)
    {
        if (userInfo["can_join_group"] && 1 == userInfo["can_join_group"])
        {
            // join group
            joinGroup(userInfo["uid"], userInfo["gid"], guessPrice);
        }
        else
        {
            alert("参团次数已经用完。");
        }
    }
    else
    {
        if (userInfo["can_create_group"] && 1 == userInfo["can_create_group"])
        {
            // create group
            setNewGroup(userInfo["uid"], userInfo["gid"], guessPrice);
        }
        else
        {
            alert("建团次数已经用完。");
        }
    }
}

function inputPriceOnKeyUp(event)
{
    if (13 == event.keyCode)
    {
        guessPriceBtnOnClick();
        return false;
    }
}

function commitExpressBtnOnClick()
{

    var nameObj = document.getElementById("name");
    var phoneObj = document.getElementById("tel");
    var addressObj = document.getElementById("add");

    var name = nameObj.value;
    var phone = phoneObj.value;
    var address = addressObj.value;

    var userInfo = JSON.parse(sessionStorage.gUserInfo);

    if (0 == userInfo["uid"] || 0 == userInfo["gid"])
    {
        return;
    }

    if (0 == name.length || 0 == phone.length || 0 == address.length)
    {
        alert("请输入完整的收货地址。");
        return;
    }

    setExpressInfo(userInfo["uid"], userInfo["gid"], name, address, phone);

}

function getGuessPriceInput()
{
    var inputObj = document.getElementById("price");
    gGuessPrice = inputObj.value;

    return gGuessPrice;
}

/* common function */
function getIntParam(paramName,defaultValue)
{
    var v = parseInt(getUrlParam(paramName));
    if(!isNaN(v))
    {
        return v;
    }
    else
    {
        return defaultValue;
    }
}

function getStringParam(paramName,defaultValue)
{
    var v = getUrlParam(paramName);
    if(v != '')
    {
        return v;
    }
    else
    {
        return defaultValue;
    }
}

function getUrlParam(param)
{
    var url = location.href;
    var c_mark = url.indexOf("#");

    if(c_mark != -1)
        url = url.substring(0,c_mark);

    var q_mark = url.indexOf("?");

    if(q_mark == -1)
        return "";

    var paraString = url.substring(q_mark+1,url.length).split("&");
    var paraObj = {};

    for(i=0; j=paraString[i]; i++)
    {
        paraObj[j.substring(0,j.indexOf("=")).toLowerCase()] = 
                j.substring(j.indexOf("=")+1,j.length);
    }

    var returnValue = paraObj[param.toLowerCase()];

    if("undefined" == typeof(returnValue))
    {
        return "";
    }
    else
    {
        return returnValue;
    }
}

function updateQueryStringParameter(uri, key, value)
{
    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
    var separator = uri.indexOf('?') !== -1 ? "&" : "?";

    if (uri.match(re)) 
    {
        return uri.replace(re, '$1' + key + "=" + value + '$2');
    }
    else 
    {
        return uri + separator + key + "=" + value;
    }
}

function removeURLParameter(url, parameter) 
{
    //prefer to use l.search if you have a location/link object
    var urlparts= url.split('?');   
    if (urlparts.length>=2) 
    {
        var prefix= encodeURIComponent(parameter)+'=';
        var pars= urlparts[1].split(/[&;]/g);

        //reverse iteration as may be destructive
        for (var i= pars.length; i-- > 0;) 
        {    
            //idiom for string.startsWith
            if (pars[i].lastIndexOf(prefix, 0) !== -1) 
            {  
                pars.splice(i, 1);
            }
        }

        if (0 < pars.length)
        {
            url = urlparts[0]+'?'+pars.join('&');
        }
        else
        {
            url = urlparts[0];
        }

        return url;
    } 
    else 
    {
        return url;
    }
}

function removeProtoFromUrl(url)
{
    var protoMatch = /^(https?):\/\//;
    return url.replace(protoMatch, '');
}

function sendRequestByGet(requestUrl, successCallback, errorCallback)
{
    sendRequest(requestUrl, "GET", null, successCallback, errorCallback);
}

function sendRequestByPost(requestUrl, data, successCallback, errorCallback)
{
    sendRequest(requestUrl, "POST", data, successCallback, errorCallback);
}

function sendRequest(requestUrl, type, data, successCallback, errorCallback)
{
    $.ajax(
    {
        url: requestUrl,
        dataType: "json",
        data: data,
        cache: false,
        timeout: AJAX_TIMEOUT,
        type: type,
        async: true,
        success:
            function(responseData, textStatus, jqXHR)
            {
                if (successCallback)
                {
                    successCallback(responseData);
                } 
            },
        error:
            function(jqXHR, textStatus, errorThrown)
            {
                // TODO: log
                // alert("request error.");
                if (0 == textStatus.status && "abort" == textStatus.statusText)
                {
                    return;
                }

                if (errorCallback)
                {
                    errorCallback();
                }

                return; 
            }
    });
}

function printResponse(responseData)
{
    var respString = "";

    for (var key in responseData)
    {
        respString += key + " => " + responseData[key] + "\n";
    }

    alert("responseData: \n" + respString);
}

function isInt(n)
{
    return Number(n) === n && n % 1 === 0;
}

function isFloat(n)
{
    return n === Number(n) && n % 1 !== 0;
}

function isValidPrice(guessPrice)
{
    guessPrice = parseFloat(guessPrice);

    if (!isInt(guessPrice) && !isFloat(guessPrice))
    {
        return false;
    }

    var price = Math.round(guessPrice * 100);

    if (Math.round(MIN_PRICE * 100) <= price  && price <= Math.round(MAX_PRICE * 100))
    {
        return true;
    }
    else
    {
        return false;
    }
}

/* example: <img id="gif_1" gif="images/气泡1.gif" width="230" height="263"> */
function gifPlayer(cont)
{
    cont.load(function(){
            cont.unbind('load'); 
            cont.show();
            });
    cont.attr('src', cont.attr('gif'));
}
