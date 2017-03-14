<?php
namespace lspbupt\wechat;
use \Yii;
use yii\caching\Cache;
use yii\di\Instance;
class WxLogin extends \lspbupt\curl\CurlHttp
{
    public $appid = "";
    public $appsecret = "";
    public $host = "api.weixin.qq.com";
    public $protocol = "https";

    public $cache = 'cache';
    const WEIXIN_CACHEKEY = "wxloginapp_cachekey";
    const WEIXIN_TOKENURL = "/sns/oauth2/access_token";
    
    private $code = "";    
    
    public function init()
    {
        parent::init();
        $this->cache = Instance::ensure($this->cache, Cache::className());
    }

    protected function afterCurl($output)
    {
        $data = json_decode($output, true);
        if(empty($output) || empty($data)) {
            $data = [
                'errcode' => 1,
                'errmsg' => '网络错误!',
            ]; 
        }
        if(empty($data['errcode'])) {
            $data = [
                'errcode' => 0,
                'errmsg' => '请求成功',
                'data' => $data,
            ];
        }
        return $data;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }
    
    public function getKey($openid)
    {
        return self::WEIXIN_CACHEKEY.":".$this->appid.":".$openid;
    }

    public function getToken()
    {
        return $this->setGet()->httpExec("/sns/oauth2/access_token", [
            'appid'=>$this->appid, 
            'secret' => $this->appsecret,
            'code' => $this->getCode(),
            'grant_type' => 'authorization_code',
        ]);
    }

    public function getTokenFromCache($openid = null)
    {
        $key = $this->getKey($openid);
        if(!$openid) {
            $arr = $this->getToken();
            if($arr['errcode'] == 0) {
                $data = $arr['data'];
                $openid = $data['openid'];
                $this->cache->set($key, $data, $data['expires_in']-60);
                return $data;
            }
            return "";
        }

        $data = $this->cache->get($key, "");
        if($data) {
            return $data;
        }
        return $this->getTokenFromCache(null);
    }

    public function refreshToken($refresh_token)
    {
        return $this->httpExec("/sns/oauth2/refresh_token", [
            "appid" => $this->appid,
            "grant_type" => "refresh_token",
            "refresh_token" => $refresh_token,
        ]); 
    }
    
    public function getRedirectUrl($redirectUrl, $scope="snsapi_userinfo", $state="")
    {
        $baseUrl = "https://open.weixin.qq.com/connect/oauth2/authorize";
        $params = [
            "appid" => $this->appid,
            "redirect_uri" => $redirectUrl,
            "response_type"=> "code", 
            "scope" => $scope,
            "state" => "STATE",
        ];
        $anchor = "#wechat_redirect";
        $url = $baseUrl."?".http_build_query($params).$anchor;
        return $url;
    }

    public function getUserInfo()
    {
        $data = $this->getTokenFromCache();
        if(empty($data)) {
            return ["errcode" => 1, "errmsg" => '网络错误']; 
        }
        $params = [
            'openid' => $data['openid'],
            'access_token' => $data['access_token'],
            'lang' => 'zh_CN',
        ];
        return $this->setGet()->httpExec("/sns/userinfo", $params);
    }
}
