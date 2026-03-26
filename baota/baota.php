<?php
function baota_idcsmartauthorizes()
{
}
function baota_MetaData()
{
    return ["DisplayName" => "宝塔对接模块(主机)", "APIVersion" => "1.0.0", "HelpDoc" => ""];
}
function baota_ConfigOptions()
{
    return [["type" => "text", "name" => "网站路径", "description" => "不懂请勿修改", "default" => "/www/wwwroot", "key" => "path"], ["type" => "text", "name" => "网站端口", "description" => "默认为80", "default" => "80", "key" => "port"], ["type" => "text", "name" => "分类ID", "description" => "默认为0", "default" => "0", "key" => "type_id"], ["type" => "text", "name" => "默认PHP版本", "description" => "格式：56，72，不懂请留空", "key" => "version"], ["type" => "yesno", "name" => "是否创建FTP", "description" => "是", "key" => "ftp"], ["type" => "text", "name" => "FTP地址", "description" => "请填写公网IP", "default" => "127.0.0.1", "key" => "ftp_server"], ["type" => "yesno", "name" => "是否创建数据库", "description" => "是", "key" => "sql"], ["type" => "text", "name" => "数据库编码类型", "description" => "utf8|utf8mb4|gbk|big5", "default" => "utf8mb4", "key" => "codeing"], ["type" => "text", "name" => "站点大小(MB)", "description" => "输入-1为不限制", "key" => "site_max"], ["type" => "text", "name" => "数据库大小(MB)", "description" => "输入-1为不限制", "key" => "sql_max"], ["type" => "text", "name" => "域名绑定数", "description" => "输入-1为不限制", "key" => "domain_num"], ["type" => "text", "name" => "网站备份数", "description" => "输入-1为不限制", "key" => "web_back_num"], ["type" => "text", "name" => "数据库备份数", "description" => "输入-1为不限制", "key" => "sql_back_num"], ["type" => "text", "name" => "月流量(MB)", "description" => "输入-1为不限制", "key" => "flow_max"], ["type" => "text", "name" => "并发数", "description" => "输入-1为不限制", "key" => "perserver"], ["type" => "text", "name" => "限制网速(KB)", "description" => "输入-1为不限制", "key" => "limit_rate"], ["type" => "dropdown", "name" => "绑定子目录", "description" => "此为高危操作，不建议开启", "options" => ["不允许", "允许"], "key" => "sub_bind"]];
}
function baota_GetHostid($params)
{
    return (int) $params["customfields"]["host_id"];
}
function baota_TestLink($params)
{
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/system?action=GetSystemTotal";
    $post_data = baota_GetKeyData($params);
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["version"]) {
        $result["status"] = 200;
        $result["data"]["server_status"] = 1;
    } else {
        $result["status"] = 200;
        $result["data"]["server_status"] = 0;
        $result["data"]["msg"] = $res["msg"];
    }
    return $result;
}
function baota_CreateAccount($params)
{
    $hostid = baota_gethostid($params);
    if (!empty($hostid)) {
        return "已开通,不能重复开通";
    }
    if (empty($params["password"])) {
        $sys_pwd = randStr(8);
    } else {
        $sys_pwd = $params["password"];
    }
    $post_data = baota_GetKeyData($params);
    $post_data["webname"] = json_encode(["domain" => $params["domain"] . ".com", "domainlist" => [], "count" => 0]);
    $post_data["path"] = $params["configoptions"]["path"] . "/" . $params["domain"];
    $post_data["type_id"] = $params["configoptions"]["type_id"];
    $post_data["type"] = "PHP";
    $post_data["port"] = $params["configoptions"]["port"];
    $post_data["version"] = $params["configoptions"]["version"];
    $post_data["ps"] = "空间:" . $params["configoptions"]["site_max"] . "MB|数据库:" . $params["configoptions"]["sql_max"];
    if ($params["configoptions"]["ftp"] == 1) {
        $post_data["ftp"] = "true";
        $post_data["ftp_username"] = $params["domain"];
        $post_data["ftp_password"] = $params["password"];
    } else {
        $post_data["ftp"] = "false";
    }
    if ($params["configoptions"]["sql"] == 1) {
        $post_data["sql"] = "true";
        $post_data["codeing"] = $params["configoptions"]["codeing"];
        $post_data["datauser"] = $params["domain"];
        $post_data["datapassword"] = $params["password"];
    } else {
        $post_data["sql"] = "false";
    }
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=AddSite";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["siteStatus"]) {
        $customid = think\Db::name("customfields")->where("type", "product")->where("relid", $params["productid"])->where("fieldname", "host_id")->value("id");
        if (empty($customid)) {
            $customfields = ["type" => "product", "relid" => $params["productid"], "fieldname" => "host_id", "fieldtype" => "text", "adminonly" => 1, "create_time" => time()];
            $customid = think\Db::name("customfields")->insertGetId($customfields);
        }
        $exist = think\Db::name("customfieldsvalues")->where("fieldid", $customid)->where("relid", $params["hostid"])->find();
        if (empty($exist)) {
            $data = ["fieldid" => $customid, "relid" => $params["hostid"], "value" => $res["siteId"], "create_time" => time()];
            think\Db::name("customfieldsvalues")->insert($data);
        } else {
            think\Db::name("customfieldsvalues")->where("id", $exist["id"])->update(["value" => $res["siteId"]]);
        }
        $mainip = $params["server_ip"];
        $update["dedicatedip"] = $mainip;
        $update["domainstatus"] = "Active";
        $update["username"] = $params["domain"];
        think\Db::name("host")->where("id", $params["hostid"])->update($update);
        return "success";
    }
    return ["status" => "error", "msg" => $res["msg"]];
}
function baota_SuspendAccount($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=SiteStop";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $post_data["name"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return ["status" => "success", "msg" => $res["msg"]];
    }
    return ["status" => "error", "msg" => $res["msg"] ?: "暂停失败"];
}
function baota_UnsuspendAccount($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=SiteStart";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $post_data["name"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return ["status" => "success", "msg" => $res["msg"]];
    }
    return ["status" => "error", "msg" => $res["msg"] ?: "解除暂停失败"];
}
function baota_TerminateAccount($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=DeleteSite";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $post_data["name"] = $params["domain"] . ".com";
    $post_data["ftp"] = 1;
    $post_data["database"] = 1;
    $post_data["path"] = 1;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return ["status" => "success", "msg" => $res["msg"]];
    }
    return ["status" => "error", "msg" => $res["msg"] ?: "删除失败"];
}
function baota_GetDomain($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/data?action=getData&table=domain";
    $post_data = baota_GetKeyData($params);
    $post_data["search"] = $hostid;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["data"];
}
function baota_AddDomain($params)
{
    $data = $_POST;
    if (empty($data["domain"])) {
        return baota_fail("域名不能为空！");
    }
    $hostid = baota_gethostid($params);
    $bt_domains = count(baota_getdomain($params));
    $allow_domains = $params["configoptions"]["domain_num"];
    if ($allow_domains !== "-1" && $allow_domains <= $bt_domains) {
        return baota_fail("域名绑定数已达上限！");
    }
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=AddDomain";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $post_data["webname"] = $params["domain"] . ".com";
    $post_data["domain"] = $data["domain"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_DeleteDomain($params)
{
    $data = $_POST;
    if (empty($data["domain"])) {
        return baota_fail("域名不能为空！");
    }
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=DelDomain";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $post_data["webname"] = $params["domain"] . ".com";
    $post_data["domain"] = $data["domain"];
    $post_data["port"] = $data["port"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetPHPVersion($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=GetPHPVersion";
    $post_data = baota_GetKeyData($params);
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res;
}
function baota_GetSitePHPVersion($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=GetSitePHPVersion";
    $post_data = baota_GetKeyData($params);
    $post_data["siteName"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["phpversion"];
}
function baota_SetPHPVersion($params)
{
    $data = $_POST;
    if (empty($data["php"])) {
        return baota_fail("PHP版本不能为空！");
    }
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=SetPHPVersion";
    $post_data = baota_GetKeyData($params);
    $post_data["siteName"] = $params["domain"] . ".com";
    $post_data["version"] = $data["php"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetKey($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/data?action=getKey";
    $post_data = baota_GetKeyData($params);
    $post_data["table"] = "sites";
    $post_data["key"] = "path";
    $post_data["id"] = $hostid;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res;
}
function baota_GetDirUserINI($params)
{
    $hostid = baota_gethostid($params);
    $path = baota_getkey($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=GetDirUserINI";
    $post_data = baota_GetKeyData($params);
    $post_data["path"] = $path;
    $post_data["id"] = $hostid;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res;
}
function baota_SetSiteRunPath($params)
{
    $data = $_POST;
    if (empty($data["runpath"])) {
        return baota_fail("运行目录不能为空！");
    }
    $hostid = baota_gethostid($params);
    $path = baota_getkey($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=SetSiteRunPath";
    $post_data = baota_GetKeyData($params);
    $post_data["runPath"] = $data["runpath"];
    $post_data["id"] = $hostid;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetRewriteList($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=GetRewriteList";
    $post_data = baota_GetKeyData($params);
    $post_data["siteName"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res;
}
function baota_GetFileBody($params, $type = "")
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/files?action=GetFileBody";
    $post_data = baota_GetKeyData($params);
    if (empty($type)) {
        $post_data["path"] = "/www/server/panel/vhost/rewrite/" . $params["domain"] . ".com.conf";
    } else {
        $post_data["path"] = "/www/server/panel/rewrite/nginx/" . $type . ".conf";
    }
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["data"];
}
function baota_SaveFileBody($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/files?action=SaveFileBody";
    $post_data = baota_GetKeyData($params);
    if ($_POST["type"] == "save" || $_POST["rewrite"] == "0.当前") {
        $post_data["data"] = $_POST["rewrite_content"];
    } else {
        $post_data["data"] = baota_getfilebody($params, $_POST["rewrite"]);
    }
    $post_data["path"] = "/www/server/panel/vhost/rewrite/" . $params["domain"] . ".com.conf";
    $post_data["encoding"] = "utf-8";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetSSL($params, $type = "")
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=GetSSL";
    $post_data = baota_GetKeyData($params);
    $post_data["siteName"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res;
}
function baota_SetSSL($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=SetSSL";
    $post_data = baota_GetKeyData($params);
    $post_data["type"] = 1;
    $post_data["siteName"] = $params["domain"] . ".com";
    $post_data["key"] = str_replace(";", PHP_EOL, base64_decode($_POST["cert_key"]));
    $post_data["csr"] = str_replace(";", PHP_EOL, base64_decode($_POST["cert_csr"]));
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($post_data["key"]);
}
function baota_CloseSSLConf($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=CloseSSLConf";
    $post_data = baota_GetKeyData($params);
    $post_data["updateOf"] = 1;
    $post_data["siteName"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_HttpToHttps($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=HttpToHttps";
    $post_data = baota_GetKeyData($params);
    $post_data["siteName"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_CloseToHttps($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=CloseToHttps";
    $post_data = baota_GetKeyData($params);
    $post_data["siteName"] = $params["domain"] . ".com";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_WebGetIndex($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=GetIndex";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res;
}
function baota_WebSetIndex($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=SetIndex";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $post_data["Index"] = $_POST["default_index"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetDeploymentList($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/deployment?action=GetList&type=0";
    $post_data = baota_GetKeyData($params);
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["list"];
}
function baota_SetupPackage($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/deployment?action=SetupPackage";
    $post_data = baota_GetKeyData($params);
    $post_data["dname"] = $_POST["dname"];
    $post_data["site_name"] = $params["domain"] . ".com";
    $post_data["php_version"] = baota_getsitephpversion($params);
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetWebBackupList($params, $type = "")
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/data?action=getData&table=backup";
    $post_data = baota_GetKeyData($params);
    $post_data["p"] = "1";
    $post_data["limit"] = "100";
    $post_data["type"] = "0";
    $post_data["tojs"] = "";
    $post_data["search"] = $hostid;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["data"];
}
function baota_WebToBackup($params, $type = "")
{
    $hostid = baota_gethostid($params);
    $bt_web_backs = count(baota_getwebbackuplist($params));
    $allow_web_backs = $params["configoptions"]["web_back_num"];
    if ($allow_web_backs !== "-1" && $allow_web_backs <= $bt_web_backs) {
        return baota_fail("网站备份数已达上限！");
    }
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=ToBackup";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $hostid;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_WebDelBackup($params, $type = "")
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/site?action=DelBackup";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $_POST["web_backup_id"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_WebFtpList($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/data?action=getData&table=ftps";
    $post_data = baota_GetKeyData($params);
    $post_data["p"] = "1";
    $post_data["limit"] = "15";
    $post_data["type"] = "-1";
    $post_data["tojs"] = "";
    $post_data["search"] = $params["domain"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["data"];
}
function baota_ResFtpPass($params)
{
    if (empty($_POST["ftp_id"])) {
        return baota_fail("FTP不能为空");
    }
    if (empty($_POST["ftp_password"])) {
        return baota_fail("FTP密码不能为空");
    }
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/ftp?action=SetUserPassword";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $_POST["ftp_id"];
    $post_data["ftp_username"] = $params["domain"];
    $post_data["new_password"] = $_POST["ftp_password"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_WebSqlList($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/data?action=getData&table=databases";
    $post_data = baota_GetKeyData($params);
    $post_data["p"] = "1";
    $post_data["limit"] = "5";
    $post_data["type"] = "-1";
    $post_data["tojs"] = "";
    $post_data["search"] = $params["domain"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["data"];
}
function baota_GetSqlInfo($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/database?action=GetInfo";
    $post_data = baota_GetKeyData($params);
    $post_data["db_name"] = $params["domain"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res;
}
function baota_ResDatabasePass($params)
{
    if (empty($_POST["db_id"])) {
        return baota_fail("数据库不能为空");
    }
    if (empty($_POST["db_password"])) {
        return baota_fail("数据库密码不能为空");
    }
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/database?action=ResDatabasePassword";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $_POST["db_id"];
    $post_data["name"] = $params["domain"];
    $post_data["password"] = $_POST["db_password"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_ReTable($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/database?action=ReTable";
    $post_data = baota_GetKeyData($params);
    $post_data["db_name"] = $params["domain"];
    $post_data["tables"] = "[\"" . $_POST["tables"] . "\"]";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_OpTable($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/database?action=OpTable";
    $post_data = baota_GetKeyData($params);
    $post_data["db_name"] = $params["domain"];
    $post_data["tables"] = "[\"" . $_POST["tables"] . "\"]";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetWebSqlList($params, $type = "")
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/data?action=getData&table=backup";
    $post_data = baota_GetKeyData($params);
    $post_data["p"] = "1";
    $post_data["limit"] = "100";
    $post_data["type"] = "1";
    $post_data["tojs"] = "";
    $post_data["search"] = baota_websqllist($params)[0]["id"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["data"];
}
function baota_GetSqlUrl($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/plugin?action=get_soft_find";
    $post_data = baota_GetKeyData($params);
    $post_data["sName"] = "phpmyadmin";
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    return $res["ext"]["url"];
}
function baota_SqlToBackup($params)
{
    $hostid = baota_gethostid($params);
    $bt_sql_backs = count(baota_getwebsqllist($params));
    $allow_sql_backs = $params["configoptions"]["sql_back_num"];
    if ($allow_sql_backs !== "-1" && $allow_sql_backs <= $bt_sql_backs) {
        return baota_fail("数据库备份数已达上限！");
    }
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/database?action=ToBackup";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = baota_websqllist($params)[0]["id"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_SqlReBackup($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/database?action=InputSql";
    $post_data = baota_GetKeyData($params);
    $post_data["file"] = $_POST["filename"];
    $post_data["name"] = $params["domain"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_SqlDelBackup($params)
{
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/database?action=DelBackup";
    $post_data = baota_GetKeyData($params);
    $post_data["id"] = $_POST["sql_backup_id"];
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    if ($res["status"]) {
        return baota_success($res["msg"]);
    }
    return baota_fail($res["msg"]);
}
function baota_GetFtpSize($params)
{
    $path = baota_getkey($params);
    $hostid = baota_gethostid($params);
    $bt_panel = baota_GetUrl($params);
    $url = $bt_panel . "/files?action=get_path_size";
    $post_data = baota_GetKeyData($params);
    $post_data["path"] = $path;
    $res = baota_HttpPostCookie($url, $bt_panel, $post_data);
    $res = json_decode($res, true);
    $size = $res["size"] / 1024 / 1024;
    $ftp_size = round($size, 2);
    return $ftp_size;
}
function baota_ClientArea($params)
{
    $tab = [];
    $tab["index"] = ["name" => "主机信息"];
    if ($params["configoptions"]["ftp"] == 1) {
        $tab["ftp"] = ["name" => "文件管理"];
    }
    if ($params["configoptions"]["sql"] == 1) {
        $tab["database"] = ["name" => "数据库管理"];
    }
    $tab["domian"] = ["name" => "域名管理"];
    $tab["php"] = ["name" => "PHP版本"];
    $tab["directory"] = ["name" => "网站目录"];
    $tab["rewrite"] = ["name" => "伪静态"];
    $tab["ssl"] = ["name" => "SSL证书"];
    $tab["default"] = ["name" => "默认文件"];
    $tab["application"] = ["name" => "应用列表"];
    $tab["web_backup"] = ["name" => "网站备份"];
    $tab["db_backup"] = ["name" => "数据备份"];
    return $tab;
}
function baota_ClientAreaOutput($params, $key)
{
    if ($key == "index") {
        return ["template" => "views/index.html", "vars" => ["params" => $params, "used_web_size" => baota_getftpsize($params), "used_sql_size" => baota_getsqlinfo($params)["data_size"], "used_domian_nums" => count(baota_getdomain($params)), "used_web_back_nums" => count(baota_getwebbackuplist($params)), "used_sql_back_nums" => count(baota_getwebsqllist($params))]];
    }
    if ($key == "ftp") {
        return ["template" => "views/ftp.html", "vars" => ["params" => $params, "ftp" => baota_webftplist($params)[0]]];
    }
    if ($key == "database") {
        return ["template" => "views/database.html", "vars" => ["params" => $params, "data_url" => baota_getsqlurl($params), "database" => baota_websqllist($params)[0], "data" => baota_getsqlinfo($params)]];
    }
    if ($key == "domian") {
        return ["template" => "views/domain.html", "vars" => ["params" => $params, "list" => baota_getdomain($params)]];
    }
    if ($key == "php") {
        return ["template" => "views/php.html", "vars" => ["params" => $params, "php" => baota_getsitephpversion($params), "list" => baota_getphpversion($params)]];
    }
    if ($key == "directory") {
        return ["template" => "views/directory.html", "vars" => ["params" => $params, "path" => baota_getkey($params), "list" => baota_getdiruserini($params)]];
    }
    if ($key == "rewrite") {
        return ["template" => "views/rewrite.html", "vars" => ["params" => $params, "rewrite_content" => baota_getfilebody($params), "list" => baota_getrewritelist($params)]];
    }
    if ($key == "ssl") {
        return ["template" => "views/ssl.html", "vars" => ["params" => $params, "data" => baota_getssl($params)]];
    }
    if ($key == "default") {
        return ["template" => "views/default.html", "vars" => ["params" => $params, "list" => baota_webgetindex($params)]];
    }
    if ($key == "application") {
        return ["template" => "views/application.html", "vars" => ["params" => $params, "panel_url" => baota_GetUrl($params), "php" => baota_getsitephpversion($params), "list" => baota_getdeploymentlist($params)]];
    }
    if ($key == "web_backup") {
        return ["template" => "views/web_backup.html", "vars" => ["params" => $params, "panel_url" => baota_GetUrl($params), "list" => baota_getwebbackuplist($params)]];
    }
    if ($key == "db_backup") {
        return ["template" => "views/db_backup.html", "vars" => ["params" => $params, "panel_url" => baota_GetUrl($params), "list" => baota_getwebsqllist($params)]];
    }
}
function baota_AllowFunction()
{
    return ["client" => ["AddDomain", "DeleteDomain", "SetPHPVersion", "SetSiteRunPath", "SaveFileBody", "SetSSL", "CloseSSLConf", "HttpToHttps", "CloseToHttps", "WebToBackup", "WebDelBackup", "SqlToBackup", "SqlDelBackup", "SqlReBackup", "SetupPackage", "WebSetIndex", "ResDatabasePass", "ReTable", "OpTable", "ResFtpPass"]];
}
function baota_GetKeyData($params)
{
    $now_time = time();
    $p_data = ["request_token" => md5($now_time . "" . md5($params["accesshash"])), "request_time" => $now_time];
    return $p_data;
}
function baota_HttpPostCookie($url, $bt_panel, $data, $timeout = 60)
{
    $cookie_file = "./" . md5($bt_panel) . ".cookie";
    if (!file_exists($cookie_file)) {
        $fp = fopen($cookie_file, "w+");
        fclose($fp);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}
function baota_GetUrl($params)
{
    $url = "";
    if ($params["secure"]) {
        $url = "https://";
    } else {
        $url = "http://";
    }
    $url .= $params["server_ip"] ?: $params["server_host"];
    if (!empty($params["port"])) {
        $url .= ":" . $params["port"];
    }
    return $url;
}
function baota_success($msg = "成功", $data = [])
{
    $array = ["code" => 200, "msg" => $msg, "data" => $data];
    return $array;
}
function baota_fail($msg = "失败", $data = [])
{
    $array = ["code" => 100, "msg" => $msg, "data" => $data];
    return $array;
}

?>