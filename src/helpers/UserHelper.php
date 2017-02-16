<?php
namespace lspbupt\wechat\helpers\UserHelper;

class UserHelper
{
    public static function getList($wechat, $next_openid="")
    {
        return $wechat->httpExec("/cgi-bin/user/get", ['next_openid' => $next_openid]);
    }
}
