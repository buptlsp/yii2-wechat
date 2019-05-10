微信小程序
--------


# 使用说明

在使用前,请先参考微信小程序的[开发文档][1]，具体的接口说明，以微信的文档为准。

本章的内容基本跟[微信开发文档][2]持一致，因此建议先阅读该文档

# 使用步骤
* 先创建小程序，获取到appid和secret
* 在网站上，添加配置，注入实例。

在yii2的web配置中，加入如下的配置，推荐放入至common的`main-local.php`中，如 `common/config/main-local.php`中。 

```php
return [
    'components' => [
        //其它配置
        //此处配置要注入的Wechat实例，key可以随便写。建议写wechat
        'smallApp' => [
            'class' => 'lspbupt\wechat\WxSmallApp',
            'appid' => '微信公众平台中的appid',
            'appsecret' => '微信公众平台中的secret',
            'cache' => 'cache', //可以不配，默认为系统使用的默认cache，如果想把access_token存入db之类的，可以配成dbcache或redisCache等，详见yii2的说明
        ],
    ],
    //....
];
```
**注意：**由于微信小程序的请求方式和wechat一样，所以WxSmallApp是Wechat的子类。因此其使用方法与Wechat完全一致。

* 发起请求：

```php
//其它需要accesstoken的请求，跟wechat一样，因此直接调wechat实例
$data = Yii::$app->smallapp->wechat
    ->setPostJson()
    ->httpExec('/datacube/getweanalysisappiddailyretaininfo', [
        'begin_date' => '20190314',
        'end_date' => '20190314'
    ]);
```

* 其它

[微信小程序的登录][3]与wechat是不一样的，本实例为它实现了对应的处理方法。
 
```php
$data = Yii::$app->smallApp->jscode2session($js_code, $grant_type);
```
 
在登录过程中，数据有加密，因此，本代码也实现了相应的解密
 
```php
//判断sign是否正确
$ret = Yii::$app->smallApp->checkSign($sessionKey, $rawData, $sign);
 
//若成功，$ret = false, 数据会在data中。若不成功，返回字符串的错误信息。
$ret = Yii::$app->smallApp->decrypt($sessionKey, $encryptedData, $iv, $data)
```





[1]: https://developers.weixin.qq.com/miniprogram/dev/api-backend/
[2]: docs/Wechat.md
[3]: https://developers.weixin.qq.com/miniprogram/dev/api-backend/auth.code2Session.html

