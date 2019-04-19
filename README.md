# yii2-wechat


## 环境条件

- php5.4或更高
- Yii2


## 安装

您可以使用composer来安装, 添加下列代码在您的``composer.json``文件中并执行``composer update``操作

```json
{
    "require": {
       "lspbupt/yii2-wechat": "dev-master"
    }
}
```
或者
```
composer require lspbupt/yii2-wechat
```
## 主要功能

在正常的业务开发过程中，和微信的对接是一个极为繁琐的事情，本软件力求将微信的对接封装起来，对外提供一个方便，舒适的开发过程，尽力降低与微信对接的难度。目前主要实现了如下内容：

 - [微信手机内h5页面的对接][1]
 - [微信手机内h5页面支付][2]
 - [微信PC端扫码登录][3]
 - [微信小程序对接][4]
 - [手机内app与微信的对接][5]

## 大致说明

### 后端对接
所有与微信后端对接的接口的使用，大致的流程均是一样的过程, 具体的可以参见前文中相应的链接：

1.注入component, 这个component是我们跟微信的桥梁，

```php
    return [
        'component' => [
            // ...
            'wechat' => [
                'class' => 'xxx', // 具体对接的类，如：ethercap/Wechat::class,
                'appid' => 'xxx',
                'appsecret' => 'xxx',
            ],
            //  其它配置
        ],
    ];
```
2.上面的配置好之后，就可以直接通过如下实例请求微信接口了。
```php
$ret = Yii::$app->wechat->httpExec('xxx', []);
```
在请求发起时，会自动从cache中读取access_token，并发出请求。如果cache中不存在，会通过微信的接口，获取access_token。用户完全不需要关心微信access_token的事情，也不需要关心加密的问题。

    
### 前端对接
所有与微信前端对接，大致的流程也均是一样的,我们只需要在前端的页面上放入如下的内容就能完全前端的对接，不需要繁琐的jsapi计算：
```php
// 具体的类详见上文中具体对接的文档
echo JSxxxConfig:: widget([
    'debug' => false, //true代表打开前端调试
    'wechat' => 'wechat', //具体的实例，对应上一节的配置名，默认为wechat
    'successJs' => 'function(){console.log("success")}', //js校验成功时的回调
    'errorJs' => 'function(){console.log("error")}', //js校验失败时的回调
    'jsApiList' => [], //需要在本页中调用的js接口
]);
```


## 广告
我们是一群热爱技术，追求卓越的极客，我们乐于做一些对整个社会都有作用的事情，我们希望通过我们的努力来推动整个社会的创新，如果你也一样，欢迎加入我们（service@ethercap.com）！你也可以通过https://tech.ethercap.com 来了解更多！


 


  [1]: docs/Wechat.md
  [2]: docs/WxPay.md
  [3]: docs/WxLogin.md
  [4]: docs/WxApp.md
  [5]: docs/WxSmallApp.md
