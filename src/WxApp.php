<?php

namespace lspbupt\wechat;

use Yii;
use yii\caching\Cache;
use yii\di\Instance;

class WxApp extends \lspbupt\curl\CurlHttp
{
    public $appid = '';
    public $appsecret = '';
    public $host = 'api.weixin.qq.com';
    public $protocol = 'https';
    public $redirectUrl = '';
    public $cache = 'cache';
    const WX_CACHEKEY = 'wxapp_cachekey';

    public function init()
    {
        parent::init();
        $this->cache = Instance::ensure($this->cache, Cache::className());
        $this->beforeRequest = function ($params, $curlhttp) {
            $action = $curlhttp->getAction();
            if ($action != '/sns/oauth2/access_token') {
                $ch = clone $curlhttp;
                $token = $ch->getTokenFromCache();
                if (strpos($action, '?') != 0) {
                    $curlhttp->setAction($action.'&access_token='.$token);
                } else {
                    $curlhttp->setAction($action.'?access_token='.$token);
                }
            }
            return $params;
        };
        $this->afterRequest = function ($output, $curlhttp) {
            $data = json_decode($output, true);
            if (empty($output) || empty($data)) {
                $data = [
                    'errcode' => 1,
                    'errmsg' => '网络错误!',
                ];
            }
            if (empty($data['errcode'])) {
                $data = [
                    'errcode' => 0,
                    'errmsg' => '请求成功',
                    'data' => $data,
                ];
            }
            return $data;
        };
    }

    public function getToken($code, $grantType = 'authorization_code')
    {
        $data = $this->setGet()->httpExec('/sns/oauth2/access_token', [
            'appid' => $this->appid,
            'secret' => $this->appsecret,
            'code' => $code,
            'grant_type' => $grantType,
        ]);
        if ($data['errcode']) {
            return $data;
        }
        $ts = time();
        $token = $data['data']['access_token'];
        $tempData = [
            'token' => $token,
            'expire' => $data['data']['expires_in'] + $ts - 100,
        ];
        Yii::$app->session->set(self::WX_CACHEKEY.$this->appid, $tempData);
        return $data;
    }

    public function getTokenFromCache($code = '', &$data = [], $grantType = 'authorization_code')
    {
        $tempData = Yii::$app->session->get(self::WX_CACHEKEY.$this->appid, '');
        $ts = time();
        if ($tempData && $tempData['expire'] > $ts) {
            return $tempData['token'];
        }
        return '';
    }

    public function getUserInfo($openid)
    {
        $params = ['openid' => $openid];
        return $this->setGet()->httpExec('/sns/userinfo', $params);
    }
}
