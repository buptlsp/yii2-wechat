微信公众号对接
------

# 使用说明

在使用前,请先参考微信公众平台的[开发文档][1]，具体的接口说明，以微信公众号的文档为准。 



# 后台对接

* 配置注入实例
在yii2的web配置中，加入如下的配置，推荐放入至common的`main-local.php`中，如 `common/config/main-local.php`中。 

```php
return [
    'components' => [
        //其它配置
        //此处配置要注入的Wechat实例，key可以随便写。建议写wechat
        'wechat' => [
            'class' => 'lspbupt\wechat\Wechat',
            'appid' => '微信公众平台中的appid',
            'appsecret' => '微信公众平台中的secret',
            'cache' => 'cache', //可以不配，默认为系统使用的默认cache，如果想把access_token存入db之类的，可以配成dbcache或redisCache等，详见yii2的说明
        ],
    ],
    //....
];
```
* 使用

正常服务器与微信后台交互需要实现[微信网页授权][3]。本软件已经帮你实现整个授权的过程，因此，我们只需要按如下使用就可以了。

```php
// 此处的wechat为上面配置中的key，因为我的配置中key为wechat,因此使用Yii::$app->wechat来与微信通信。
Yii::$app->wechat->setGet()->httpExec($action, $params);
Yii::$app->wechat->setPost()->httpExec($action, $params);
Yii::$app->wechat->setPostJson()->httpExec($action, $params);
//调试，如果我们想要输出调试信息
Yii::$app->wechat->setDebug()->httpExec($action, $params);

//示例：如下为获取用户信息的接口（https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140840），我们只需要写如下代码即可。不需要关心access_token等问题。
$data = Yii::$app->wechat->httpExec('/cgi-bin/user/get', []);
// 临时上传素材示例（https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1444738726）
$data = Yii::$app->wechat
    ->setPost()
    ->setFormData()
    ->httpExec('/cgi-bin/media/upload?type=image', [
        'media' => new \CURLFile(realpath(test.jpg)),
    ]);
```

* 接口返回封装

在正常情况下，我们的请求可能会出现网络，超时等情况。无论出现什么异常，数据返回的结果的格式均是如下的格式：

```javascript
{
    //错误码，参见微信接口文档。非微信返回原因的错误（如网络超时），代码为1。
    "errcode" : 0,
    // 错误信息
    "errmsg" : "错误信息",
    // 当成功时的数据。
    "data" : {},
}
```

* 接口调试示例

对于wechat的基本功能的使用，可以参见我的另一个[开源项目][2],可以按其中的说明，配置好命令行工具，这样我们就可以在命令行敲命令来调试微信接口了。如下图所示为第一次发起/cig-bin/user/get接口，由于没有access_token,所以先获取access_token,然后再发起/cgi-bin/user/get请求,整个过程用户无感知。

![wechat工具第一次请求示意](/docs/img/wechat_console1.jpg)

如下为第二次请求（上传素材），由于已经在cache中有了access_token，本次直接发起请求。
![wechat工具后续请求示意](/docs/img/wechat_console2.jpg)


# JSSDK
在微信公众号中，与[前端的对接][4]也是一个麻烦的事情.本软件也提供了一个极为便利的方式来解决与微信对接时，计算token的情况。
在view页面，加入如下代码即可：

```php
<?php
echo JsapiConfig::widget([
    'debug' => false, //可省略，默认为false
    'wechat' => 'wechat', //可省略，对应前面配置的wechat实例的名字
    'successJs' => 'function(){console.log("test");}', //js校验成功时的js回调，用户可以自行做自己想做的操作。
    'errorJs' => 'function(){console.log("test");}', //js校验失败时的js回调，用户可以自行做一些错误提示及上报等。
    'jsApiList' => ['chooseImage'], //在本页面使用的js接口列表。本处示例为选择图片
]);
?>
```

加上上面的代码后，我们就完成了整个前端jssdk的申请和校验过程。我们不需要按照文档引入js文件，也不需要自己计算jsapi_ticket。

**PS:** 目前不支持前后端分离模式。

# 微信消息回调
在微信公众号中，有很多[事件推送][5]，这些推送一是有加解密算法，二是全都由xml组成，不便于开发。本文以微信公众号自动回复为例，讲解本软件的使用方式：

* 在某个controller(本例中假设为TestController)中，添加如下的代码,

```php
class TestController extend Controller
{
    //其它代码
    
    // 默认Action
    public actions()
    {
        return [
            //如前面一样,key是url，可以自行定义。本例中url为/test/wx-callback
            'wxCallback' => [
                'class' => 'lspbupt\wechat\actions\CallbackAction',
                // 自己配置的token
                'token' => YIIDEBUG ? "test123" : 'xxx',
                // 当微信通知过来时，我们做的处理回调。我们可以在函数内任意处理。
                'processFunc' => function($postArr) {
                     return xxHelper::process($postArr);
                },
            ],
        ];
    }
}
```
* 在[微信的后台][6]，配置好回调的地址和token。按上面的配置话，我们的配置为：
    * 回调地址：yourdomain.com/test/wx-callback
    * token： 线下test123,线上xxx
    * 目前并没有实现密文模式，因此请先择明文模式。后续如果各位有需求，可以提issue给我,我来添加。
* 写具体的回调执行代码就可以了。
    * 所有的xml都转换为了postArr，我们不需要关心具体的内容。
    * 在返回数据时，我们直接return数组就可以了，系统会自动转换为xml，大致格式如下：
   
    ```php
        // 设置返回的格式，这样会触发系统的格式转换，将数组转换为xml
        Yii::$app->response->format = Response::FORMAT_XML;
        // 在processFunc中，return数组
        return [
            "" => "xxx"
        ];
    ```
    * 正常情况下，本代码针对[微信的被动回复消息][7]提供了许多便利函数
    
    ```php
        // 回复文本消息
        \lspbupt\wechat\actions\CallbackAction::replyTextMsg($content, $from, $to, $createTime);
        // 回复图片消息
        \lspbupt\wechat\actions\CallbackAction::replyImageMsg($mediaId, $from, $to, $createTime)
        // 回复语音消息
        \lspbupt\wechat\actions\CallbackAction::replyVoiceMsg($mediaId, $from, $to, $createTime)
        // 回复视频消息
        \lspbupt\wechat\actions\CallbackAction::replyMusicMsg($mediaId, $titile, $desc, $from, $to, $createTime)
        // 回复音乐消息
        \lspbupt\wechat\actions\CallbackAction::replyImageMsg($title, $desc, $musicUrl, $hqUrl, $thumbMediaId, $from, $to, $createTime)
        // 其它消息等
    ```
 


[1]: https://mp.weixin.qq.com/wiki
[2]: https://github.com/buptlsp/yii2-curl
[3]: https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140842
[4]: https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141115
[5]: https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140454
[6]: https://mp.weixin.qq.com/advanced/advanced?action=dev&t=advanced/dev&token=589568611&lang=zh_CN
[7]: https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140543
