<?php

namespace lspbupt\wechat\actions;

use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\web\Response;
use Closure;
use lspbupt\wechat\helpers\XmlResponseFormatter;
use lspbupt\wechat\Wechat;

//微信公众号回调接口

class CallbackAction extends Action
{
    public $token;
    public $processFunc;

    public function init()
    {
        parent::init();
        $this->controller->enableCsrfValidation = false;
        if (empty($this->processFunc)) {
            throw new InvalidConfigException('请配置处理函数');
        }
        if (empty($this->token)) {
            throw new InvalidConfigException('请配置token');
        }
        if (!($this->processFunc instanceof Closure)) {
            throw new InvalidConfigException('处理函数必须是Closure');
        }
        //自动处理xml，将rawBody中的xml直接转换为Yii::$app->request->post()
        Yii::$app->request->parsers = [
            'application/xml' => [
                'class' => '\bobchengbin\Yii2XmlRequestParser\XmlRequestParser',
                'priority' => 'tag',
            ],
            'text/xml' => [
                'class' => '\bobchengbin\Yii2XmlRequestParser\XmlRequestParser',
                'priority' => 'tag',
            ],
        ];
        Yii::$app->response->formatters = [
            Response::FORMAT_XML => [
                'class' => '\lspbupt\wechat\helpers\XmlResponseFormatter',
            ],
        ];
        Yii::$app->response->format = Response::FORMAT_RAW;
    }

    public function run()
    {
        $arr = Yii::$app->request->get();
        $arr['token'] = $this->token;
        if (self::checkSign($arr)) {
            $postArr = Yii::$app->request->post();
            if (empty($postArr)) {
                return ArrayHelper::getValue($arr, 'echostr', '');
            }
            $data = call_user_func($this->processFunc, $postArr);
            return $data;
        }
        return 'Token Error!';
    }

    public static function checkSign($arr)
    {
        $signature = ArrayHelper::getValue($arr, 'signature', '');
        $timestamp = ArrayHelper::getValue($arr, 'timestamp', '');
        $nonce = ArrayHelper::getValue($arr, 'nonce', '');
        $token = ArrayHelper::getValue($arr, 'token', '');
        return $signature == self::getSign($token, $timestamp, $nonce);
    }

    public static function getSign($token, $timestamp, $nonce)
    {
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        return sha1($tmpStr);
    }

    public static function getCdataArr($str)
    {
        return [$str, XmlResponseFormatter::CDATA => true];
    }

    public static function baseMsg($type, $dataArr, $from, $to, $createTime)
    {
        Yii::$app->response->format = Response::FORMAT_XML;
        empty($createTime) && $createTime = (string) time();
        empty($from) && $from = Yii::$app->request->post('ToUserName', '');
        empty($to) && $to = Yii::$app->request->post('FromUserName', '');
        return [
            'ToUserName' => self::getCdataArr($to),
            'FromUserName' => self::getCdataArr($from),
            'CreateTime' => $createTime,
            'MsgType' => self::getCdataArr(strtolower($type)),
            $type => $dataArr,
        ];
    }

    public static function replyTextMsg($content, $from = null, $to = null, $createTime = null)
    {
        $arr = self::baseMsg('Text', self::getCdataArr($content), $from, $to, $createTime);
        $arr['Content'] = $arr['Text'];
        unset($arr['Text']);
        return $arr;
    }

    public static function replyImageMsg($mediaId, $from = null, $to = null, $createTime = null)
    {
        $data = ['MediaId' => self::getCdataArr($mediaId)];
        return self::baseMsg('Image', $data, $from, $to, $createTime);
    }

    public static function replyVoiceMsg($mediaId, $from = null, $to = null, $createTime = null)
    {
        $data = ['MediaId' => self::getCdataArr($mediaId)];
        return self::baseMsg('Voice', $data, $from, $to, $createTime);
    }

    public static function replyVideoMsg($mediaId, $titile = '', $desc = '', $from = null, $to = null, $createTime = null)
    {
        $data = [
            'MediaId' => self::getCdataArr($mediaId),
            'Title' => self::getCdataArr($title),
            'Description' => self::getCdataArr($desc),
        ];
        return self::baseMsg('Video', $data, $from, $to, $createTime);
    }

    public static function replyMusicMsg($title = '', $desc = '', $musicUrl = '', $hqUrl = '', $thumbMediaId = '', $from = null, $to = null, $createTime = null)
    {
        $data = [
            'Title' => self::getCdataArr($title),
            'Description' => self::getCdataArr($desc),
            //音乐链接
            'MusicURL' => self::getCdataArr($musicUrl),
            //高质量音乐链接，WIFI环境优先使用该链接播放音乐
            'HQMusicUrl' => self::getCdataArr($hqUrl),
            // 缩略图的媒体id
            'ThumbMediaId' => self::getCdataArr($thumbMediaId),
        ];
        return self::baseMsg('Music', $data, $from, $to, $createTime);
    }

    public static function replyNewsMsg($items, $from = null, $to = null, $createTime = null)
    {
        $arr = self::baseMsg('News', [], $from, $to, $createTime);
        unset($arr['News']);
        $articles = [];
        $keys = ['Title', 'Description', 'Url', 'PicUrl'];
        foreach ($items as $item) {
            $temp = [];
            foreach ($keys as $key) {
                $temp[$key] = self::getCdataArr(ArrayHelper::getValue($item, $key, ''));
            }
            $articles[] = $temp;
        }
        $arr['ArticleCount'] = count($articles);
        $arr['Articles'] = $articles;
        return $arr;
    }

    public static function replySingleMsg($title = '', $desc = '', $url = '', $picUrl = '', $from = null, $to = null, $createTime = null)
    {
        $items = [
            [
                'Title' => $title,
                'Description' => $desc,
                'PicUrl' => $picUrl,
                'Url' => $url,
            ],
        ];
        return self::replyNewsMsg($items, $from, $to, $createTime);
    }

    /**
     * @var array[] replyXxxMsg返回的数组的数组
     * @var Wechat 微信组件
     * @return array
     */
    public static function replyMultipleMsg(array $messages, Wechat $wechat)
    {
        if (!$wechat instanceof Wechat) {
            throw new InvalidConfigException('若批量回复多条消息，必须配置相应的Wechat组件以主动发送消息！');
        }
        $interval = (int) (100 * 1000 / count($messages));
        $lastMessage = array_pop($messages);
        foreach ($messages as $message) {
            $message = self::xmlArrResponseToJsonRequestForCustomMessage($message);
            $wechat->setPostJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ->send('/cgi-bin/message/custom/send', $message);
            usleep($interval);
        }
        return $lastMessage;
    }

    /**
     * @author 杨国帅 [ygs@cgcal.com]
     * @var array 通过self::getCdataArr得到的待转为XML的数组。
     * @see https://mp.weixin.qq.com/wiki?action=doc&id=mp1421140547
     * @return array
     */
    private static function xmlArrResponseToJsonRequestForCustomMessage($array)
    {
        $jsonArray = [
            'touser' => $array['ToUserName'][0],
            'msgtype' => $array['MsgType'][0],
        ];
        switch ($jsonArray['msgtype']) {
            case 'text':
                $jsonArray['text'] = [
                    'content' => $array['Content'][0],
                ];
                break;
            case 'image':
                $jsonArray['image'] = [
                    'media_id' => $array['Image']['MediaId'][0],
                ];
                break;
            case 'voice':
                $jsonArray['voice'] = [
                    'media_id' => $array['Voice']['MediaId'][0],
                ];
                break;
            case 'news':
                $article = $array['Articles'][0];
                $jsonArray['news'] = [
                    'articles' => [[
                        'title' => $article['Title'][0],
                        'description' => $article['Description'][0],
                        'url' => $article['Url'][0],
                        'picurl' => $article['PicUrl'][0],
                    ]],
                ];
                break;
            default:
                break;
        }
        return $jsonArray;
    }
}
