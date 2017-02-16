yii2-wechat-sdk
===============
环境条件
--------
- >= php5.4
- Yii2

安装
----

=======
##安装
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
=======
##使用示例

在使用前,请先参考微信公众平台的[开发文档](http://mp.weixin.qq.com/wiki/index.php?title=%E9%A6%96%E9%A1%B5)

- Wechat定义方式

```php
//在config/web.php配置文件中定义component配置信息
'components' => [
  .....
  'wechat' => [
    'class' => 'lspbupt\wechat\Wechat',
    'appid' => '微信公众平台中的appid',
    'appsecret' => '微信公众平台中的secret',
    'token' => '微信服务器对接您的服务器验证token'
  ]
  ....
]
// 全局公众号sdk使用
$wechat = Yii::$app->wechat; 
//多公众号使用方式
$wechat = Yii::createObject([
    'class' => 'lspbupt\wechat\Wechat',
    'appid' => '微信公众平台中的appid',
    'appsecret' => '微信公众平台中的secret',
    'token' => '微信服务器对接您的服务器验证token'
]);
```
- Wechat方法使用(部分示例)

```php

$wechat = Yii::$app->wechat;

```
