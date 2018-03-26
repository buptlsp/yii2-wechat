<?php
namespace lspbupt\wechat;
use \Yii;
use yii\caching\Cache;
use yii\di\Instance;
use yii\helpers\Inflector;
class Wechat extends \lspbupt\curl\CurlHttp
{
    public $appid = "";
    public $appsecret = "";
    public $host = "api.weixin.qq.com";
    public $protocol = "https";

    public $cache = 'cache';
    const WEIXIN_TOKENURL = "/cgi-bin/token";
    const WEIXIN_CACHEKEY = "weixin_cachekey";
    const WEIXIN_JSAPI_CACHEKEY = 'weixin_jaapi_cachekey';

    public function init()
    {
        parent::init();
        $this->cache = Instance::ensure($this->cache, Cache::className());
        $this->beforeRequest = function($params, $curlhttp) {
            $action = $curlhttp->getAction();
            if($action != self::WEIXIN_TOKENURL) {
                $ch = clone $curlhttp;
                $token = $ch->getTokenFromCache();
                if(strpos($action, "?") != 0) {
                    $curlhttp->setAction($action."&access_token=".$token);
                } else {
                    $curlhttp->setAction($action."?access_token=".$token);
                }
            }
            return $params; 
        };
        $this->afterRequest = function($output, $curlhttp) {
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
        };
    }

    public function getToken()
    {
        return $this->setGet()->httpExec(self::WEIXIN_TOKENURL, ['appid'=>$this->appid, 'secret' => $this->appsecret, 'grant_type' => 'client_credential']);
    }

    public function getTokenFromCache()
    {
        $token = $this->cache->get(self::WEIXIN_CACHEKEY.$this->appid, "");
        if($token) {
            return $token;
        }
        $arr = $this->getToken();
        if($arr['errcode'] == 0) {
            $access_token = $arr['data']['access_token'];
            $this->cache->set(self::WEIXIN_CACHEKEY.$this->appid, $access_token, 3600);
            return $access_token;
        }
        return "";
    }

    public function getJsapiTicket()
    {
        return $this->setGet()
            ->httpExec("/cgi-bin/ticket/getticket", ['type' => 'jsapi']); 
    }

    public function getJsapiTicketFromCache()
    {
        $jsapitoken = $this->cache->get(self::WEIXIN_JSAPI_CACHEKEY.$this->appid, "");
        if($jsapitoken) {
            return $jsapitoken;
        }
        $arr = $this->getJsapiTicket();
        if($arr['errcode'] == 0) {
            $jsapitoken = $arr['data']["ticket"];
            $expire = $arr['data']['expires_in'];  
            $this->cache->set(self::WEIXIN_JSAPI_CACHEKEY.$this->appid, $jsapitoken, $expire-60);
            return $jsapitoken;
        }
        return "";
    }

    public function JsSign(&$arr=[]) 
    {
        empty($arr['url']) && $arr['url'] = "";
        empty($arr['timestamp']) && $arr['timestamp'] = time();
        empty($arr['noncestr']) && $arr['noncestr'] = \Yii::$app->security->generateRandomString(10);
        empty($arr['jsapi_ticket']) && $arr['jsapi_ticket'] = $this->getJsapiTicketFromCache();
        $plain = 'jsapi_ticket=' . $arr['jsapi_ticket'] .
            '&noncestr=' . $arr['noncestr'] .
            '&timestamp=' . $arr['timestamp'] .
            '&url=' . $arr['url'];
        return sha1(str_replace('|', '%7C', $plain));
    }

    public function __call($method, $args)
    {
        $classPrefix = '\lspbupt\wechat\helpers\\';
        $arr = explode("_", $method);
        if(count($arr) == 2) {
            $className = $classPrefix.Inflector::id2camel($arr[0]).'Helper';
            $methodName = $arr[1];
            //如果class 和方法均存在，则调用helper中的静态方法
            array_unshift($args, $this);
            if(class_exists($className) && method_exists($className, $methodName)) {
                return call_user_func_array([$className, $methodName], $args); 
            }
        } 
        throw new \Exception($method.":该方法不存在");
    }

}
