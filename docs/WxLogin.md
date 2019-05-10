外部网站微信内登录对接
----

# 使用说明

在使用前,请先参考微信公众平台自动登录的[开发文档][1]，具体的接口说明，以微信的文档为准。

# 使用步骤
* 先按文档的要求,得到appid和appsecret<大部分情况下与微信公众号的一致>
* 在网站上，添加配置。

在yii2的web配置中，加入如下的配置，推荐放入至common的`main-local.php`中，如 `common/config/main-local.php`中。 
  
  ```php
  return [
    'components' => [
        //其它配置
        //此处配置要注入的WxLogin实例，key可以随便写。建议写wxapp
        'wxLogin' => [
            'class' => 'lspbupt\wechat\WxLogin',
            'appid' => '公众号的appid',
            'appsecret' => '公众号的secret',
            // 可以不配，默认为系统使用的默认cache，如果想把access_token存入db之类的，可以配成dbcache或redisCache等，详见yii2的说明
            'cache' => 'cache',   
        ],
    ],
    //....
];
  ```
  
* 在页面上根据环境判断，自动跳转，等待用户同意授权

如下为示意代码，用户可以自行书写：

```javascript
//获取判断用的ua对象
var ua = navigator.userAgent.toLowerCase();
if (ua.match(/MicroMessenger/i) === "micromessenger") {
    // 跳转到wx登录的地址
}
```
一般情况下，我们可以这样来获取跳转至微信的地址

```php
// $redirectUrl为我们想要跳回的地址、
$loginUrl = Yii::$app->wxLogin->getRedirectUrl($redirectUrl);
```

* 在用户点击确认同意后，微信会跳回我们的前面设置的redirectUrl，并带上code参数。
这样，我们就可以controller内开始按微信登录的逻辑书写代码，如下为大致示意代码：

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
        $userInfo = Yii::$app->wxLogin->setCode($code)->getUserInfo();
        if ($data['errcode']) {
            //...
        }
       //余下省略，开发者可以根据用户的信息进行登录操作或者储存至数据库等操作。
    }
}
```

