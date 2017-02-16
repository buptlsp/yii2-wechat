<?php
namespace lspbupt\wechat;
use \Yii;
use yii\base\Widget;
use yii\web\JsExpression;
use yii\base\InvalidConfigException;
use lspbupt\wechat\Wechat;
use lspbupt\wechat\assets\WechatAsset;
class JsapiConfig extends Widget
{
    public $debug = false;
    public $wechat = 'wechat';
    public $successJs;
    public $errorJs;
    public $jsApiList = [];

    public function init()
    {
        if (is_string($this->wechat)) {
            $this->wechat = Yii::$app->get($this->wechat);
        } elseif (is_array($this->wechat)) {
            if (!isset($this->wechat['class'])) {
                $this->wechat['class'] = Wechat::className();
            }
            $this->wechat = Yii::createObject($this->wechat);
        }
        if (!$this->wechat instanceof wechat) {
            throw new InvalidConfigException("微信配置错误");
        }
    }

    public function getUrl()
    {
        $request = \Yii::$app->request;
        $url = $request->hostInfo.urldecode($request->getUrl());
        return $url;  
    }

    public function run()
    {
        $view = $this->getView();
        WechatAsset::register($view);
        $arr = [
            'url' => $this->getUrl(),
        ];
        $sign = $this->wechat->JsSign($arr);
        $js ="wx.config({
             appId: '".$arr['corpid']."',//必填，企业ID
             timeStamp: ".$arr['timestamp'].", // 必填，生成签名的时间戳
             nonceStr: '".$arr['noncestr']."', // 必填，生成签名的随机串
             signature: '".$sign."', // 必填，签名
             jsApiList: ".json_encode($this->jsApiList)." // 必填，需要使用的jsapi列表
        });
        wx.ready(".$this->successJs.");
        wx.error(".$this->errorJs.");";
        $view->registerJs($js);
    }
}
