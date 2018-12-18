<?php

namespace lspbupt\wechat\helpers;

class UserHelper
{
    public static function get($wechat, $next_openid = '')
    {
        return $wechat->httpExec('/cgi-bin/user/get', ['next_openid' => $next_openid]);
    }
}
