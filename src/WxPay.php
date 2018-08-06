<?php

namespace lspbupt\wechat;

use Yii;
use Closure;
use InvalidArgumentException;
use yii\di\Instance;
use yii\web\Response;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use bobchengbin\Yii2XmlRequestParser\Xml2Array;
use bobchengbin\Yii2XmlRequestParser\XmlRequestParser;
use lspbupt\wechat\helpers\XmlResponseFormatter;

class WxPay extends \lspbupt\curl\CurlHttp
{
    const PAY_CODE_OK = 0;
    const PAY_CODE_NETWORK_FAIL = 1;
    const PAY_CODE_REQUEST_FAIL = 10;
    const PAY_CODE_PAY_FAIL = 20;

    const PAY_TRADE_TYPE_JSAPI = 'JSAPI';
    const PAY_TRADE_TYPE_NATIVE = 'NATIVE';
    /**
     * @var string 商户号，见开户邮件
     */
    public $mch_id;
    /**
     * @var string 商户密钥，自己设置
     */
    public $mch_key;

    public $host = 'api.mch.weixin.qq.com';
    public $protocol = 'https';

    /**
     * @var string 退款证书，支持yii别名
     */
    public $apiclient_cert = '';
    /**
     * @var string 退款证书密钥，支持yii别名
     */
    public $apiclient_key = '';
    /**
     * @var string 回调地址
     */
    public $notify_url;
    /**
     * @var WxApp 微信组件，包含appid和appSecret
     */
    public $wxapp = 'wxapp';

    private $optional = [];

    public function init()
    {
        parent::init();
        $this->wxapp = Instance::ensure(Yii::$app->{$this->wxapp}, WxApp::class);
        $this->beforeRequest = Closure::fromCallable([$this, 'beforeRequest']);
        $this->afterRequest = Closure::fromCallable([$this, 'afterRequest']);
    }

    public function buildJsapiParams(string $prepayId): array
    {
        $data = [
            'appId' => $this->wxapp->appid,
            'timeStamp' => (string) time(),
            'nonceStr' => self::nonceStr(),
            'package' => "prepay_id=$prepayId",
            'signType' => 'MD5',
        ];
        return self::sign($data, $this->mch_key, 'paySign');
    }

    public function unifiedorder($body, $tradeNo, $totalFee, array $params = [])
    {
        return $this->httpExec('/pay/unifiedorder', array_merge([
            'body' => $body,
            'out_trade_no' => $tradeNo,
            'total_fee' => $totalFee,
            'notify_url' => $this->notify_url,
        ], $params));
    }

    public function orderquery()
    {
        return $this->httpExec('/pay/orderquery', $this->withOptional([
            'out_trade_no', 'transaction_id',
        ]));
    }

    public function closeorder(string $tradeNo)
    {
        return $this->httpExec('/pay/closeorder', ['out_trade_no' => $tradeNo]);
    }

    public function refund(string $refundNo, int $totalFee, int $refundFee)
    {
        return $this->setCert()->httpExec('/secapi/pay/refund', $this->withOptional([
            'out_trade_no',
            'transaction_id',
        ], [
            'out_refund_no' => $refundNo,
            'total_fee' => $totalFee,
            'refund_fee' => $refundFee,
            'notify_url' => $this->notify_url,
        ]));
    }

    public function refundquery()
    {
        return $this->httpExec('/pay/refundquery', $this->withOptional([
            'out_trade_no',
            'transaction_id',
            'out_refund_no',
            'refund_id',
        ]));
    }

    public function setOptional(string $key, $value): self
    {
        if (!$this->optional && !empty($value)) {
            $this->optional[$key] = $value;
        }
        return $this;
    }

    protected function withOptional(array $range, array $params = []): array
    {
        if (!$this->optional) {
            throw new InvalidArgumentException('Optional args is required.');
        }
        if (!in_array(key($this->optional), $range)) {
            throw new InvalidArgumentException('Optional arg is not included in the range.');
        }
        $params = array_merge($params, $this->optional);
        $this->optional = [];
        return $params;
    }

    public static function beforeRequest($params, self $self)
    {
        $self->setPost();
        $self->setFormData();
        $params = array_merge([
            'appid' => $self->wxapp->appid,
            'mch_id' => $self->mch_id,
            'nonce_str' => self::nonceStr(),
        ], $params);
        $signedData = self::sign($params, $self->mch_key);
        $xmlData = self::arrayToXml($signedData);
        return $xmlData;
    }

    public static function afterRequest($data, self $self)
    {
        $response = function ($code = 0, $message = 'OK', $parsedData = []) use ($data) {
            return [
                'code' => $code,
                'data' => [
                    'raw' => $data,
                    'parsed' => $parsedData,
                ],
                'message' => $message,
            ];
        };
        $data = self::xmlToArray($data);
        if (!$data || !is_array($data)) {
            return $response(1, '未知错误');
        }
        if (ArrayHelper::getValue($data, 'return_code', 'FAIL') === 'FAIL') {
            return $response(
                10,
                '通信错误：'.ArrayHelper::getValue($data, 'return_message', '未知错误'),
                $data
            );
        }
        if (ArrayHelper::getValue($data, 'result_code', 'FAIL') === 'FAIL') {
            return $response(
                20,
                '业务错误：'.ArrayHelper::getValue($data, 'err_code', '未知错误代码').
                    '：'.ArrayHelper::getValue($data, 'err_code_des', '未知错误代码描述'),
                $data
            );
        }
        return $response(0, 'success', $data);
    }

    private static function nonceStr()
    {
        $chars = 'ABCDEFGHJKMNPRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < 32; ++$i) {
            $str .= $chars[mt_rand(0, 31)];
        }
        return $str;
    }

    private static function sign(array $data, string $mch_key, string $signKey = 'sign'): array
    {
        $data[$signKey] = self::getSign($data, $mch_key);
        return $data;
    }

    private static function getSign(array $data, string $mch_key)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            //xml计算签名不算空的值。所以key也不能要。
            $value && $str .= "$key=$value&";
        }
        $str .= "key=$mch_key";
        return strtoupper(md5($str));
    }

    private static function arrayToXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $xml .= '<'.$key.'>'.$value.'</'.$key.'>';
            } else {
                $xml .= '<'.$key.'><![CDATA['.$value.']]></'.$key.'>';
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    private static function xmlToArray(string $xml, string $root = 'xml'): ?array
    {
        $ret = ArrayHelper::getValue(Xml2Array::go($xml), $root, null);
        if (!is_array($ret) || $ret === null) {
            return null;
        }
        return $ret;
    }

    private function setCert()
    {
        $this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->setOpt(CURLOPT_SSL_VERIFYHOST, false);
        $this->setOpt(CURLOPT_SSLCERTTYPE, 'PEM');
        $this->setOpt(CURLOPT_SSLCERT, Yii::getAlias($this->apiclient_cert));
        $this->setOpt(CURLOPT_SSLKEYTYPE, 'PEM');
        $this->setOpt(CURLOPT_SSLKEY, Yii::getAlias($this->apiclient_key));
        return $this;
    }

    public function callbackAction(Closure $processData): string
    {
        $callback = new class('', '', []) extends Action {
            public static $checkSign;
            public static $processData;

            public function init()
            {
                if (!$this->controller) {
                    return;
                }
                parent::init();
                $this->controller->enableCsrfValidation = false;
                if (empty(self::$processData)) {
                    throw new InvalidConfigException('请配置处理函数');
                }
                if (!(self::$processData instanceof Closure)) {
                    throw new InvalidConfigException('处理函数必须是Closure');
                }
                //自动处理xml，将rawBody中的xml直接转换为Yii::$app->request->post()
                Yii::$app->request->parsers = [
                    'application/xml' => [
                        'class' => XmlRequestParser::class,
                        'priority' => 'tag',
                    ],
                    'text/xml' => [
                        'class' => XmlRequestParser::class,
                        'priority' => 'tag',
                    ],
                ];
                Yii::$app->response->formatters = [
                    Response::FORMAT_XML => [
                        'class' => XmlResponseFormatter::class,
                    ],
                ];
                Yii::$app->response->format = Response::FORMAT_XML;
            }

            public function run()
            {
                $post = Yii::$app->request->post();
                if (call_user_func_array(self::$checkSign, [&$post])) {
                    return call_user_func(self::$processData, $post);
                }
                return [
                    'return_code' => 'FAIL',
                    'return_msg' => '签名失败',
                ];
            }
        };
        $callback::$checkSign = Closure::fromCallable([$this, 'checkDecodeSign']);
        $callback::$processData = $processData;
        return get_class($callback);
    }

    private function checkDecodeSign(array &$data, $signKey = 'sign'): bool
    {
        $sign = ArrayHelper::remove($data, $signKey);
        if ($sign !== null) {
            return $sign === self::getSign($data, $this->mch_key);
        }
        $reqInfo = ArrayHelper::remove($data, 'req_info');
        if ($reqInfo !== null && self::decode($reqInfo, $data, $this->mch_key)) {
            return true;
        }
        return false;
    }

    private static function decode(string $src, array &$dst, string $mch_key): bool
    {
        $srcDecode = base64_decode($src, true);
        $key = md5($mch_key);
        $data = openssl_decrypt($srcDecode, 'AES-256-ECB', $key, OPENSSL_RAW_DATA, '');
        if ($data === false) {
            return false;
        }
        $decodeData = self::xmlToArray($data, 'root');
        if ($decodeData === null) {
            return false;
        }
        $dst = array_merge($dst, $decodeData);
        return true;
    }
}
