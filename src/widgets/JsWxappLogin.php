<?php

namespace lspbupt\wechat\widgets;

use Yii;
use yii\base\Widget;
use yii\base\InvalidConfigException;
use lspbupt\wechat\WxApp;
use lspbupt\wechat\assets\WxappLoginAsset;

class JsWxappLogin extends Widget
{
    public $debug = false;
    public $wxApp = 'wxapp';
    public $id = 'login_container';
    public $scope = 'snsapi_login';
    public $redirect_uri = '';
    public $state = '';
    public $style = 'black';
    public $href = '';

    public function init()
    {
        if (is_string($this->wxApp)) {
            $this->wxApp = Yii::$app->get($this->wxApp);
        } elseif (is_array($this->wxApp)) {
            if (!isset($this->wxApp['class'])) {
                $this->wxApp['class'] = WxApp::className();
            }
            $this->wxApp = Yii::createObject($this->wxApp);
        }
        if (!$this->wxApp instanceof WxApp) {
            throw new InvalidConfigException('微信配置错误');
        }
    }

    public function run()
    {
        $view = $this->getView();
        WxappLoginAsset::register($view);
        $js = 'var obj = new WxLogin({
            id:"'.$this->id.'", 
            appid: "'.$this->wxApp->appid.'", 
            scope: "'.$this->scope.'", 
            redirect_uri: "'.$this->redirect_uri.'",
            state: "'.$this->state.'",
            style: "'.$this->style.'",
            href: "'.$this->href.'"
        }); ';
        $view->registerJs($js);
    }
}
