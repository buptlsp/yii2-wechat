微信支付对接
------

# 使用说明
在使用前,请先参考微信支付商户的普通商户的[开发文档][1]，具体的接口说明，以微信的文档为准。

# WxPay为你提供了：
  * 自动处理`开发者`向`微信服务器`请求的请求/响应格式
    * 自动加上对应的请求签名
  * 自动处理`微信服务器`向`开发者`回调的请求/响应
    * 自动解密微信回调数据
    * 安全回调（防止xml 实体攻击）
  * 关键api实现
    * 统一下单
    * 关单
    * 退款
    * 订单查询
    * 退款查询
  * 回调action自动注入
    * 支付回调
    * 退款回调

  理论上，一切和微信服务器对接相关的脏活累活，WxPay都为你实现了，你需要关注你的业务逻辑即可。

# 准备工作
本文以公众号支付为基础，介绍公众号支付，扫码支付，小程序支付，H5支付，APP支付等常用的支付手段，
以及退款/订单查询等在支付过程中必要的接口调用。

# 支付申请
使用微信公众号支付，你需要：
* 可用的微信公众号（服务号）
* 和服务号同主体的微信商户开通

在此基础上，可以开通：
* 扫码支付
* H5支付

如果你有一个小程序，在小程序关联微信支付商户之后，你可以：
* 小程序支付

APP支付需要单独申请（申请见[微信开放平台][2]），与公众号支付无法公用一个微信商户

# 后台对接

* 配置注入实例
在yii2的web配置中，加入如下的配置，推荐放入至common的`main-local.php`中，如 `common/config/main-local.php`中。

```php
return [
    'components' => [
        //其它配置
        //此处配置要注入的Wxpay实例，key可以随便写。建议写wxpay
        'wxpay' => [
            'class' => '\lspbupt\wechat\WxPay',
            'payappid' => '商户对应的公众号id',
            'mch_id' => '商户id',
            'mch_key' => '商户密钥',
            'apiclient_cert' => '@common/cert/apiclient_cert.pem',
            'apiclient_key' => '@common/cert/apiclient_key.pem',
            'notify_url' => 'https://url.to/pay/callback',
        ],
    ],
    //....
];
```

## 配置获取说明
* payappid 公众号的appid
  * 因为一个商户对应的微信服务可能有很多个，默认是公众号的appid
  * 在小程序支付需要手动修改小程序的appid
* mch_id/mch_key 商户api密钥，见[支付账户][3]
* apiclient_cert/apiclient_key 商户api证书
  * 路径支持yii2别名
  * 申请见[安全规范][4]
* 回调地址
  * 默认的处理回调通知的地址

# API调用
  ## 基本调用
  见[Wechat](Wechat.md)
  ## 可选参数机制
  当我们调用WxPay的公有实例方法的时候，有部分参数选择一个提供即可，api本身会变得比较冗长
  因此，我们引入了可选参数函数，让开发者能够方便的传入接口api调用的必要参数。
  ### `Wechat::setOptional`
  ```php
  public function setOptional(string $key, $value): self;
  Yii::$app->wechat->setOptional('out_trade_no', 'foo|bar|123')->orderquery();
  Yii::$app->wechat->setOptional('transaction_id', '12312321212')->orderquery();
  ```

  如果每个可选参数都没有提供，将会抛出异常。

  ## 统一下单
  ```php
  public function unifiedorder($body, $tradeNo, $totalFee, array $params = [])
  ```
  微信文档见[jsapi][5]

  API参数对应微信文档
  * body 商品描述 body
  * tradeNo 微信的out_trade_no
  * totalFee 单位分 totalFee
  * params 对于不同的支付方式的补充参数不一样
    * jsapi（公众号支付，小程序支付）
      ```php
      [
          'trade_type' => WxPay::PAY_TRADE_TYPE_JSAPI,
          'spbill_create_ip' => $remoteIp,//支付用户的remoteip，
          'openid' => $openid,//支付用户的openid
      ];
      ```
      支付用户的openid，获取见[微信登录](WxLogin.md)
      注意，如果给用户更好的体验，最好用静默登录
      如果小程序支付，首先调用WxPay::setPayAppid()设置小程序的appid之后再下单；微信支付小程序的用户openid，见[WxSmallApp文档](WxSmallApp.md)
   * 扫码支付
     ```php
      [
          'trade_type' => WxPay::PAY_TRADE_TYPE_NATIVE,
          'product_id' => ‘PRODUCT_123',//商品id，可以用out_trade_no或其他逻辑。
      ];
      ```
   * H5支付
     ```php
     'trade_type' => WxPay::PAY_TRADE_TYPE_MWEB,
     'spbill_create_ip' => $remoteIp,//支付用户的remoteip，
     'scene_info' => json_encode([
         'h5_info' => 'ANDROID, XXXAPP',//上报的app信息
     ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
     ```

  返回值和错误
   * 返回`$ret`统一转换为数组，具体规范见[jsapi][5]
   * xml解析之后的数据放在`$ret['parsed']`中
   * 最关键的是prepayId，留作后续支付用
## 用户支付
  ### JSAPI支付
  利用WxPay::buildJsapiParams(string $prepayId): array方法得到支付参数之后，传给前端调起支付。
  调起支付的示例代码：
  ```javascript
  function onBridgeReady(){
   WeixinJSBridge.invoke(
       'getBrandWCPayRequest', <?=json_encode($jsapiParams, JSON_PRETTY_PRINT); ?>,
       function(res){
           if(res.err_msg == "get_brand_wcpay_request:ok") {
               //处理已支付
               return;
           } else if (res.err_msg == "get_brand_wcpay_request:cancel") {
               //处理取消支付
               return;
           } else if (res.errMsg == "chooseWXPay:fail, the permission value is offline verifying") {
               console.log("微信支付将在真机调起。");
               alert("微信支付将在真机调起。");
               return;
           }
           return;
        }
   );
  }
  if (typeof WeixinJSBridge == "undefined"){
    if( document.addEventListener ){
        document.addEventListener('WeixinJSBridgeReady', onBridgeReady, false);
    }else if (document.attachEvent){
        document.attachEvent('WeixinJSBridgeReady', onBridgeReady);
        document.attachEvent('onWeixinJSBridgeReady', onBridgeReady);
    }
  } else {
    onBridgeReady();
  }
  ```
  ### NATIVE（扫码支付）
  将返回的`$ret['data']['parsed']['code_url']`展示给用户引导用户付款

  ### H5支付
  将返回的`$ret['data']['parsed']['mweb_url']`交给前端在合适的时候跳转

  #### 在android/ios APP 嵌入H5支付
  后续补充。
## 订单查询
  WxPay::orderquery()

  可选参数：out_trade_no/transaction_id
## 关单
  WxPay::closeOrder($tradeNo)

  因为关闭的订单一定还没有支付，所以没有流水号可选。
## 退款
  WxPay::refund($refundNo, int $totalFee, int $refundFee)

  refundNo: 唯一退款号，自行生成

  totalFee: 支付总金额

  refundFee: 退款金额

## 退款查询
  WxPay::refundquery()

  可选参数：out_trade_no/transaction_id/out_refund_no/refund_id 之一即可

  注意，如果用支付参数查询，会查到多笔退款

# 回调处理
  在需要处理的controller注入回调方法
  ```php
    class FooController extends Controller
    {
        public function actions()
        {
            return [
                'bar' => Yii::$app->wxpay->callbackAction(
                    function ($post) {
                        if(isset($post['refund_id'])) {
                           $result = Baz::processAfterRefund($post);
                        } else {
                           $result = Boo::processAfterPay($post);
                        }
                        if ($result) {
                          return [
                              'return_code' => 'SUCCESS',
                              'return_msg' => 'OK',
                          ];
                        }
                        return [
                            'return_code' => 'FAIL',
                            'return_msg' => '处理错误',
                        ];
                    }
                ),
            ];
        }
    }
  ```
  当程序错误或者我们手动返回处理失败的时候，微信服务器会进行多次重试，请及时处理。
  支付结果通知的格式见[支付结果通知][6]
  退款结果通知的格式见[退款结果通知][7]，已完成对加密数据的解密工作。

[1]: https://pay.weixin.qq.com/wiki/doc/api/index.html
[2]: https://open.weixin.qq.com
[3]: https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=3_1
[4]: https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=4_3
[5]: https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
[6]: https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_7&index=8
[7]: https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_16&index=10


# 附录
 ##为啥要用可选参数？
 如果不用，我们的api和调用会长成这个样子：

 ### api:
  ```php
  public function orderquery($out_trade_no = '', $transaction_id = '') {
    $params = [];
    if($out_trade_no) {
      $params['out_trade_no'] = $out_trade_no;
    } else if ($transaction_id) {
      $params['transaction_id'] = $transaction_id;
    } else {
      throw Exception();
    }
    #...
  }
  ```
  ### call:
  ```php
  Yii::$app->wechat->orderquery('foo|bar|123');
  Yii::$app->wechat->orderquery('', '1231123111');
  ```

  如果有多个可选参数，需要一个即可，无论是调用还是api实现都将成为噩梦。
