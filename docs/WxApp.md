外部网站微信扫码登录
----

# 使用说明

在使用前,请先参考微信开放平台扫码登录的[开发文档][1]，具体的接口说明，以微信的文档为准。

# 使用步骤
* 先按文档的要求，在`微信开放平台`创建网页app,得到appid和appsecret
* 在网站上，添加配置。

在yii2的web配置中，加入如下的配置，推荐放入至common的`main-local.php`中，如 `common/config/main-local.php`中。 
  
  ```php
  return [
    'components' => [
        //其它配置
        //此处配置要注入的WxLogin实例，key可以随便写。建议写wxapp
        'wxapp' => [
            'class' => 'lspbupt\wechat\WxApp',
            'appid' => '开放网页app的appid',
            'appsecret' => '开放平台网页app的secret',
            // 可以不配，默认为系统使用的默认cache，如果想把access_token存入db之类的，可以配成dbcache或redisCache等，详见yii2的说明
            'cache' => 'cache',   
        ],
    ],
    //....
];
  ```
  
* 在页面上放入微信的二维码

具体的配置参见[开发文档][1]，本widget与它保持一致。在页面加入如下代码即可，不用引js，也不用作额外的加解密等。
 
 ```php 
 <div>
    <div id="login_container">
    </div>
 </div>
 
 <?=\lspbupt\wechat\widgets\JsWxappLogin::widget([
        //与前面的配置名保持一致
        'wxApp' => 'wxapp',
        // 该id很重要，会把微信扫码的二维码填充至该id对应的dom内。默认为login_container
        'id' => 'login_container',
        // 微信扫码成功后，页面的回跳地址，处理回跳逻辑
        'redirect_uri' => 'xxx',
        // scope,state等均见开发文档的说明，也可以不配置。
        'state' => 'xxx',
        'style' => 'black',
        'href' => '...',
 ]);
 ?>
 ```

* 在controller内开始按微信登录的逻辑书写代码，如下为大致示意代码：

```php
class xxController extends Controller
{
    public function actionWxlogin()
    {
        $code = Yii::$app->request->get("code", "");
        //如果没有带code参数，直接展示上文中的扫码登录页
        if(empty($code)) {
           //如果有state,设置state,并存储
            return $this->render("xxx");
        }
        //检查state,如果有的话,代码省略

        //根据code获取用户信息
        $data = Yii::$app->wxApp->getToken($code);
        if ($data['errcode']) {
            //...
        }
       $unionid = ArrayHelper::getValue($data, 'data.unionid', '');
       $openid = ArrayHelper::getValue($data, 'data.openid', '');
       //根据openid获取用户信息
       $data = Yii::$app->$wxApp->getUserInfo($openid);
       //余下省略，开发者可以根据用户的信息进行登录操作或者储存至数据库等操作。
    }

}


```


[1]: https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419316505&token=&lang=zh_CN

