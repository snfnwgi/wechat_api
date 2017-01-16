<?php

require_once 'libs/phpqrcode/qrlib.php';
require_once 'libs/function.php';

class WebWeixin
{
    private $id;
    private $uuid;
    private $appid = 'wx782c26e4c19acffb';
    private $redirect_uri;
    private $base_uri;
    private $skey;
    private $sid;
    private $uin;
    private $pass_ticket;
    private $BaseRequest;
    private $cookie_jar;
    private $SyncKey;
    private $User;
    private $device_id;
    private $synckey;
	private $files = array();
    private $syncCheck_num = 0;

    // 我的所有用户数
    private $member_count = 0;
    // 我的所有用户列表
    private $member_list = array();

    // 我的公众号列表
    private $public_user_list = array();
    // 我的直接联系人列表
    private $contact_list = array();
    // 我的群列表
    private $group_list = array();

    // 我的特殊账号列表
    private $special_user_list = array();

    // 群内成员
    private $group_member_list = array();

    // 已知特殊账号列表
    private $special_users = array(
        'newsapp', 'fmessage', 'filehelper', 'weibo', 'qqmail', 'fmessage', 'tmessage', 'qmessage', 'qqsync', 'floatbottle', 'lbsapp', 'shakeapp', 'medianote', 'qqfriend', 'readerapp', 'blogapp', 'facebookapp', 'masssendapp', 'meishiapp', 'feedsapp', 'voip', 'blogappweixin', 'weixin', 'brandsessionholder', 'weixinreminder', 'wxid_novlwrv3lqwv11', 'gh_22b87fa7cb3c', 'officialaccounts', 'notification_messages', 'wxid_novlwrv3lqwv11', 'gh_22b87fa7cb3c', 'wxitil', 'userexperience_alarm', 'notification_messages'
    );

    private $bot_member_list = array();


    public function __construct()
    {
        $this->cookie_jar = tempnam(sys_get_temp_dir(), 'wx_webapi');
        $this->device_id = 'e'.rand(100000000000000, 999999999999999);
    }

    /**
     * 设置ID
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * 获取 UUID
     * @return bool
     */
    public function getUUID()
    {
        $url = 'https://login.weixin.qq.com/jslogin';

        $params = array(
            'appid' => $this->appid,
            'fun' => 'new',
            'lang' => 'zh_CN',
            '_' => time()
        );

        $data = $this->_post($url, $params, false);

        $regx = '#window.QRLogin.code = (\d+); window.QRLogin.uuid = "(\S+?)"#';

        preg_match($regx, $data, $res);

        if ($res) {
            $code = $res[1];
            $this->uuid = $res[2];

            return $code == '200';
        }

        return false;
    }


    /**
     * 生成登录二维码
     */
    public function genQRCodeImg()
    {
        $url = 'https://login.weixin.qq.com/l/'.$this->uuid;

        QRcode::png($url, 'saved/'.$this->uuid.'.png', 'L', 4, 2);

        exec('open '.'saved/'.$this->uuid.'.png');

        return true;
    }


    /**
     * 检测是否扫描二维码登录
     * @param int $tip
     * @return bool
     */
    public function waitForLogin($tip=1)
    {
        $url = sprintf('https://login.weixin.qq.com/cgi-bin/mmwebwx-bin/login?tip=%s&uuid=%s&_=%s', $tip, $this->uuid, time());


        $data = $this->_get($url);

        if ($data == false) {
            return false;
        }

        $regx = '#window.code=(\d+);#';

        preg_match($regx, $data, $res);

        $code = $res[1];

        if ($code == '201') {
            return true;
        } elseif ($code == '200') {
            $regx = '#window.redirect_uri="(\S+?)";#';

            preg_match($regx, $data, $res);

            $r_uri = $res[1].'&fun=new';

            $this->redirect_uri = $r_uri;
            $this->base_uri = substr($r_uri, 0, strrpos($r_uri, '/'));
            return true;
        } elseif ($code == '408') {
            _echo('登录超时');
        } else {
            _echo('登录异常');
        }

        return false;
    }


    /**
     * 执行登录
     */
    public function login()
    {
        $data = $this->_get($this->redirect_uri);

        $xml = simplexml_load_string($data);

        $arr_xml = json_decode(json_encode($xml), true);

        $this->skey = $arr_xml['skey'];
        $this->sid = $arr_xml['wxsid'];
        $this->uin = $arr_xml['wxuin'];
        $this->pass_ticket = $arr_xml['pass_ticket'];

        if (in_array('', array($this->skey, $this->sid, $this->uin, $this->pass_ticket))) {
            return false;
        }

        $this->BaseRequest = array(
            'Uin' => intval($this->uin),
            'Sid' => $this->sid,
            'Skey' => $this->skey,
            'DeviceID' => $this->device_id
        );

        return true;
    }


    /**
     * 微信初始化
     */
    public function webWxInit()
    {
        $url = sprintf($this->base_uri . '/webwxinit?r=%i&lang=en_US&pass_ticket=%s', time(), $this->pass_ticket);

        $params = json_encode(array('BaseRequest'=>$this->BaseRequest));

        $data = $this->_post($url, $params);

        $arr_data = json_decode($data, true);

        if ($arr_data['BaseResponse']['Ret'] != 0) {
            return false;
        }

        $this->SyncKey = $arr_data['SyncKey'];
        $this->User = $arr_data['User'];

        $synckey_list = array();
        foreach ($this->SyncKey['List'] as $item) {
            $synckey_list[] = $item['Key'].'_'.$item['Val'];
        }

        $this->synckey = implode('|', $synckey_list);

        return true;
    }


    /**
     * 消息通知
     * @return bool
     */
    public function webWxStatusNotify()
    {
        $url = sprintf($this->base_uri.'/webwxstatusnotify?lang=zh_CN&pass_ticket=%s', $this->pass_ticket);

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'Code' => 3,
            'FromUserName' => $this->User['UserName'],
            'ToUserName' => $this->User['UserName'],
            'ClientMsgId' => time()
        );

        $data = $this->_post($url, json_encode($params));

        $arr_data = json_decode($data, true);

        return $arr_data['BaseResponse']['Ret'] == 0;
    }


    /**
     * 获取联系人列表
     * @return bool
     */
    public function webWxGetContact()
    {
        $url = sprintf($this->base_uri.'/webwxgetcontact?pass_ticket=%s&skey=%s&r=%s', $this->pass_ticket, $this->skey, time());

        $data = $this->_post($url , array());

        $arr_data = json_decode($data, true);

        //file_put_contents('/tmp/data.json', $data);

        $this->member_count = $arr_data['MemberCount'];
        $this->member_list = $arr_data['MemberList'];
        $contact_list = $this->member_list;
        $public_user_list = $this->public_user_list;

        foreach ($contact_list as $k=>$v) {
            if (($v['VerifyFlag'] & 8) != 0) {  // 公众号/服务号
                unset($contact_list[$k]);
                $public_user_list[] = $v;
            } elseif (in_array($v['UserName'], $this->special_users)) {   // 特殊账号
                unset($contact_list[$k]);
                $this->special_user_list[] = $v;
            } elseif (strpos($v['UserName'], '@@') !== false) { // 群聊
                unset($contact_list[$k]);
                $this->group_list[] = $v;
            } elseif ($v['UserName'] == $this->User['UserName']) {  // 自己
                unset($contact_list[$k]);
            }
        }

        $this->contact_list = $contact_list;

        return true;
    }


    /**
     * 获取群信息
     * @return bool
     */
    public function webWxBatchGetContact()
    {
        $url = $this->base_uri.sprintf('/webwxbatchgetcontact?type=ex&r=%s&pass_ticket=%s', time(), $this->pass_ticket);

        $list = array();

        foreach ($this->group_list as $v) {
            $item = array();
            $item['UserName'] = $v['UserName'];
            $item['EncryChatRoomId'] = '';
            $list[] = $item;
        }

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'Count' => count($this->group_list),
            'List' => $list
        );

        $data = $this->_post($url, json_encode($params));

        $arr_data = json_decode($data, true);

        $contact_list = $arr_data['ContactList'];

        $this->group_list = $contact_list;

        foreach ($contact_list as $contact) {
            foreach ($contact['MemberList'] as $member) {
                $this->group_member_list[] = $member;
            }
        }

        return true;
    }


    /**
     * 同步刷新
     * @return bool
     */
    public function syncCheck()
    {

        $params = array(
            'r' => time(),
            'sid' => $this->sid,
            'uin' => $this->uin,
            'skey' => $this->skey,
            'devicedid' => $this->device_id,
            'synckey' => $this->synckey,
            '_' => time()
        );

        $url = $this->base_uri.'/synccheck?'.http_build_query($params);

        $data = $this->_get($url);

        if ($data == false) {
            return array('retcode'=>0, 'selector'=>0);
        }

        $regx = '#window.synccheck={retcode:"(\d+)",selector:"(\d+)"}#';

        preg_match($regx, $data, $res);

        $retcode = $res[1];
        $selector = $res[2];

        switch ($retcode) {
            case 0:
                _echo('同步数据轮次: '.++$this->syncCheck_num);
                break;
            default:
                _echo('同步数据失败 或 登出微信');
                exit();
        }

        _echo('retcode: '.$retcode.', selector: '.$selector);

        return array('retcode'=>$retcode, 'selector'=>$selector);
    }


    /**
     * 获取消息
     * @return mixed
     */
    public function webWxSync()
    {

        $url = sprintf($this->base_uri.'/webwxsync?sid=%s&skey=%s&pass_ticket=%s', $this->sid, $this->skey, $this->pass_ticket);

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'SyncKey' => $this->SyncKey,
            'rr' => ~time()
        );

        $data = $this->_post($url, json_encode($params));;

        $arr_data = json_decode($data, true);

        if ($arr_data['BaseResponse']['Ret'] == '0') {
            $this->SyncKey = $arr_data['SyncKey'];

            $synckey_list = array();
            foreach ($this->SyncKey['List'] as $item) {
                $synckey_list[] = $item['Key'].'_'.$item['Val'];
            }

            $this->synckey = implode('|', $synckey_list);
        }

        return $arr_data;
    }


    /**
     * 监听消息
     */
    public function listenMsgMode()
    {
        _echo('进入消息监听模式 ... 成功');

        $while_num = 0;

        while (true) {

            $start = time();
            $sync_check = $this->syncCheck();
            _echo('耗时: '.(time()-$start).'s');

            if ($sync_check['retcode'] == 0) {
                $res = $this->webWxSync();

                if (is_null($res)) {
                    _echo('意外退出 ...');
                    exit();
                }

                // 记录每次同步的响应信息
                $log_data = array();
                $log_data['selector'] = $sync_check['selector'];
                $log_data['wx_sync'] = $res;
                _save_data($log_data, $this->id);

                switch ($sync_check['selector']) {
                    // 同步正常
                    case 0:
                        _echo('本次同步正常');

                        break;
                    // 有新消息
                    case 2:
                        _echo('有新的消息');

                        // 空消息
                        if ($res['AddMsgCount'] == 0) {
                            sleep(1);
                        }

                        $this->handleMsg($res);

                        break;

                    // 联系人有更新
                    case 4:
                        _echo('好友信息有变动, 更新联系人列表');

                        foreach ($res['ModContactList'] as $member) {
                            $this->member_list[] = $member;
                        }

                        break;

                    case 7:
                        _echo('进入或离开聊天界面');

                        //goto start;
                        exit();
                        break;

                    // 同意添加对方为好友
                    case 6:
                        _echo('同意添加对方为好友, 更新联系人列表');

                        $this->handleMsg($res);
                        foreach ($res['ModContactList'] as $member) {
                            $this->member_list[] = $member;
                        }

                        break;

                    default:
                        $res = $this->webWxSync();
                        _echo('意外退出 ...');
                        exit();
                }
            }

            sleep(1);

            // 进程状态
            $id_info = array('status'=>5);
            //set_cache($this->id, $id_info);

            // 保持在线
            $online_list[] = $this->id;
            //set_cache('online_list', array_unique($online_list));

            $while_num++;

            if ($while_num%10 == 0) {
                _echo('开启状态通知 ...', $this->webWxStatusNotify());
            }

        }
    }


    public function getNameById($id)
    {
        $url = $this->base_uri.sprintf('/webwxbatchgetcontact?type=ex&r=%s&pass_ticket=%s', time(), $this->pass_ticket);

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'Count' => 1,
            'List' => array(array('UserName'=>$id, 'EncryChatRoomId'=>''))
        );

        $data = $this->_post($url, json_encode($params));

        $arr_data = json_decode($data, true);

        return $arr_data['ContactList'];
    }


    /**
     * 获取群名称
     * @param $id
     * @return string
     */
    public function getGroupName($id)
    {
        $name = '未知群';

        foreach ($this->group_list as $member) {
            if ($member['UserName'] == $id) {
                $name = $member['NickName'];
            }
        }

        if ($name == '未知群') {
            $group_list = $this->getNameById($id);

            foreach ($group_list as $group) {
                $this->group_list[] = $group;

                if ($group['UserName'] == $id) {
                    $name = $group['NickName'];

                    foreach ($group['MemberList'] as $member) {
                        $this->group_member_list[] = $member;
                    }
                }
            }
        }

        return $name;
    }


    /**
     * 获取用户ID
     * @param $name
     * @return string
     */
    public function getUserId($name)
    {
        $id = '';

        foreach ($this->member_list as $member) {
            if (in_array($name, array($member['RemarkName'], $member['NickName']))) {
                $id = $member['UserName'];
                break;
            }
        }

        return $id;
    }


    /**
     * 根据ID获取名称
     * @param $id
     */
    public function getUserRemarkName($id)
    {

        $name = '陌生人';

        if (substr($id, 0, 2) == '@@') {
            $name = '未知群';
        }

        if ($id == $this->User['UserName']) {
            return $this->User['NickName'];
        }

        if (substr($id, 0, 2) == '@@') {
            // 群
            $name = $this->getGroupName($id);
        } else {
            // 特殊账号
            foreach ($this->special_user_list as $member) {
                if ($member['UserName'] == $id) {
                    $name = !empty($member['RemarkName']) ? $member['RemarkName'] : $member['NickName'];
                }
            }

            // 公众号
            foreach ($this->public_user_list as $member) {
                if ($member['UserName'] == $id) {
                    $name = !empty($member['RemarkName']) ? $member['RemarkName'] : $member['NickName'];
                }
            }

            // 直接联系人
            foreach ($this->contact_list as $member) {
                if ($member['UserName'] == $id) {
                    $name = !empty($member['RemarkName']) ? $member['RemarkName'] : $member['NickName'];
                }
            }

            // 群友
            foreach ($this->group_member_list as $member) {
                if ($member['UserName'] == $id) {
                    $name = !empty($member['RemarkName']) ? $member['RemarkName'] : $member['NickName'];
                }
            }
        }

        return $name;
    }


    public function handleMsg($res)
    {
        foreach ($res['AddMsgList'] as $msg) {

            $msg_type = $msg['MsgType'];
            $from_username = $msg['FromUserName'];
            $msgid = $msg['MsgId'];
            $content = $msg['Content'];

            $search = array('&gt;', '&lt;', '<br/>');
            $replace = array('>', '<', '');

            $content = str_replace($search, $replace, $content);

            _echo('消息类型: '. $msg_type);
            _echo('原始消息内容: '. $content);


            switch ($msg_type) {
                // 文本消息
                case 1:

                    // 控制退出
                    if ($from_username == $this->User['UserName'] && $content == '退出托管') {
                        $this->_webWxSendmsg('退出托管成功', $this->User['UserName']);
                        $this->logout();
                        exit();
                    }

                    if ($content == '开启') {
                        $this->bot_member_list[$from_username] = 1;
                        $this->_webWxSendmsg('已开始机器人回复模式', $from_username);
                        return ;
                    }

                    if ($content == '关闭') {
                        unset($this->bot_member_list[$from_username]);
                        $this->_webWxSendmsg('已关闭机器人回复模式', $from_username);
                        return ;
                    }

                    $this->_showMsg($msg);

//                    if (in_array($from_username, array_keys($this->bot_member_list))) {

                        $answer = $this->_tuling_bot($content, $from_username);

                        $this->_webWxSendmsg($answer, $from_username);
//                    }

                    break;

				// 图片消息
				case 3:
					break;
				
				// 语音消息
				case 34:
					break;

                // 状态提示
                case 51:

                    $res = array();
                    preg_match("#id='(\d)'#", $content, $res);
                    $id = $res[1];

                    $res = array();

                    preg_match("#<username>(.*)</username>#", $content, $res);

                    $username = isset($res[1]) ? $res[1] : null;

                    switch ($id) {
                        case 2:
                            _echo('进入聊天界面->'.$username);
                            break;
                        case 5:
                            _echo('退出聊天界面->'.$username);
                            break;
                        case 4:
                            _echo('未读消息通知');
                            break;
                        case 9:
                            _echo('朋友圈有更新');
                            break;
                    }

                    break;

                // 加好友提示
                case 37:
                    _echo('有人加我为好友, 请审核');
                    break;

                // 同意对方加好友请求后的系统提示语
                case 10000:
                    //_echo('你已添加了Vicky，现在可以开始聊天了');
                    break;
            }
        }
    }


    /**
     * 上传文件
     * @param $file
     * @return bool
     */
    private function _uploadmedia($file)
    {
        $url = 'https://file.wx.qq.com/cgi-bin/mmwebwx-bin/webwxuploadmedia?f=json';
        $clientMsgId = time()*1000 . rand(1000, 9999);

        $cookie_data = file($this->cookie_jar);

        $cookies = array();
        foreach ($cookie_data as $v) {
            $tmp_data = explode("\t", $v);

            if (isset($tmp_data[5])) {
                $cookies[$tmp_data[5]] = trim($tmp_data[6]);
            }
        }
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($finfo, $file);
        $data = array(
            'id' => 'WU_FILE_1',
            'name' => basename($file),
            'type' => $mime_type,
            'lastModifiedDate' => date('D M m Y H:i:s').' GMT+0800 (CST)',
            'size' => filesize($file),
            'mediatype' => 'pic',
            'uploadmediarequest' => json_encode(array(
                'BaseRequest' => $this->BaseRequest,
                'ClientMediaId' => $clientMsgId,
                'TotalLen' => filesize($file),
                'StartPos' => 0,
                'DataLen' => filesize($file),
                'MediaType' => 4
            )),
            'webwx_data_ticket' => $cookies['webwx_data_ticket'],
            'pass_ticket' => $this->pass_ticket,
            'filename' => curl_file_create($file, $mime_type, basename($file))
        );

        $header = array('Content-Type: multipart/form-data');

        $res = $this->_post_header($url, $data, $header);

        $res = json_decode($res, true);

        if ($res['BaseResponse']['Ret'] != 0) {
            return false;
        }
		echo '上传成功'.PHP_EOL;	
        return $res['MediaId'];
    }


    /**
     * 发送图片信息
     * @param $file
     * @param $user
     * @return bool
     */
    private function _webWxSendimg($file, $user)
    {
        $url = $this->base_uri.'/webwxsendmsgimg?fun=async&f=json';
        $clientMsgId = time()*1000 . rand(1000, 9999);

		if (isset($this->files[$file])) {
			$media_id = $this->files[$file];
		} else {
			$media_id = $this->_uploadmedia($file);
			$this->files[$file] = $media_id;
		}

        if (empty($media_id)) {
            _echo('上传图片失败');
            return false;
        }
		echo 'media_id: ' . $media_id . PHP_EOL;

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'Msg' => array(
                'Type' => 3,
                'MediaId' => $media_id,
                'FromUserName' => $this->User['UserName'],
                'ToUserName' => $user,
                'LocalID' => $clientMsgId,
                'ClientMsgId' => $clientMsgId
            )
        );

        $data = $this->_post($url, json_encode($params, JSON_UNESCAPED_UNICODE));

        $arr_data = json_decode($data, true);

        return $arr_data['BaseResponse']['Ret'] == 0;
    }

    /**
     * 发送文本消息
     * @param $content
     * @param $user
     * @return bool
     */
    private function _webWxSendmsg($content, $user)
    {
        $url = sprintf($this->base_uri.'/webwxsendmsg?pass_ticket=%s', $this->pass_ticket);
        $clientMsgId = time()*1000 . rand(1000, 9999);

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'Msg' => array(
                'Type' => 1,
                'Content' => $content,
                'FromUserName' => $this->User['UserName'],
                'ToUserName' => $user,
                'LocalID' => $clientMsgId,
                'ClientMsgId' => $clientMsgId
            )
        );

        $data = $this->_post($url, json_encode($params, JSON_UNESCAPED_UNICODE));

        $arr_data = json_decode($data, true);

        return $arr_data['BaseResponse']['Ret'] == 0;
    }


    /**
     * 图灵机器人
     * @param $query
     * @param $userid
     * @return mixed
     */
    private function _tuling_bot($query, $userid)
    {
        $url = 'http://www.tuling123.com/openapi/api';

        $params = array(
            'key' => 'a0dc5c2edd76999392a9bf45533ab758',
            'info' => $query,
            'userid' => $userid
        );

        $data = $this->_post($url, json_encode($params));

        $arr_data = json_decode($data, true);

        return $arr_data['text'];
    }


    /**
     * 一个AI机器人
     * @param $query
     * @param $userid
     * @return mixed
     */
    private function _yigeai_bot($query, $userid)
    {
        $data = array(
            'token' => '20F21FED84B1BC7F88C798C90FBAEBBB',
            'query' => $query,
            'session_id' => md5($userid)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://www.yige.ai/v1/query');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);

        $arr_res = json_decode($response, true);

        return $arr_res['answer'];
    }


    /**
     * 显示文本信息
     * @param $message
     */
    private function _showMsg($message)
    {
        $from_username = $this->getUserRemarkName($message['FromUserName']);
        $to_username = $this->getUserRemarkName($message['ToUserName']);
        $group_name = '';
        $search = array('&lt;', '&gt;');
        $replace = array('<', '>');

        $content = str_replace($search, $replace, $message['Content']);
        $message_id = $message['MsgId'];

        if (substr($message['FromUserName'], 0, 2) == '@@') {
            if (strpos($message['Content'], ':<br/>') !== false) {
                list($group_member, $content) = explode(':<br/>', $content);
                $group_name = $from_username;
                $from_username = $this->getUserRemarkName($group_member);
            } else {
                $group_name = '系统';
            }

        }

        if ($group_name == '微信功能测试群') {
            $to_group_name = 'test技术部';
        }

        if ($group_name == 'test技术部') {
            $to_group_name = '微信功能测试群';
        }

        if (substr($message['ToUserName'], 0, 2) == '@@') {
            $group_name = $to_username;
            $to_username = $this->getUserRemarkName($message['ToUserName']);
        }

        _echo('MsgId: '. $message_id);

        if (!empty($group_name)) {
            _echo('群聊: '.$group_name);
        }

        _echo('From: '.$from_username);
        _echo('To: '.$to_username);


        $res = array();
        preg_match_all("/@([^\s\xe2\x80\x85]+)\xe2\x80\x85/", $content, $res);

        if (isset($res[1])) {

            foreach ($res[1] as $at_name) {
                _echo('@昵称: '.$at_name);
                _echo('@ID: '.$this->getUserId($at_name));
            }
        }

        _echo('消息内容: '.$content);
        _echo('');
    }


    /**
     * 退出登录
     */
    public function logout()
    {
        $url = sprintf('https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxlogout?redirect=1&type=0&skey=%s', $this->skey);

        $params = array(
            'sid' => $this->sid,
            'uin' => $this->uin
        );

        $this->_post($url, $params, false);
		exit();
    }


    /**
     * 自定义header头 POST请求
     * @param $url
     * @param $params
     * @return bool|mixed
     */
    private function _post_header($url, $params, $header=null)
    {
        $ch = curl_init();

        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_jar);

        $data = curl_exec($ch);

        if (curl_errno($ch)) {
            //print "Error: " . curl_error($ch);
            return false;
        } else {
            return $data;
        }
    }


    /**
     * GET请求
     * @param $url
     * @return bool|mixed
     */
    private function _get($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, 'https://wx.qq.com/');
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_jar);

        $data = curl_exec($ch);

        if (curl_errno($ch)) {
            _echo(' Error: ' . curl_error($ch));
            return false;
        } else {
            return $data;
        }
    }

    /**
     * POST请求
     * @param $url
     * @param $params
     * @return bool|mixed
     */
    private function _post($url, $params, $jsonfmt=true)
    {
        $ch = curl_init();
        if ($jsonfmt) {
            $header = array(
                'Content-Type: application/json; charset=UTF-8',
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_jar);
        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            //print "Error: " . curl_error($ch);
            return false;
        } else {
            return $data;
        }
    }


    /**
     * 运行
     */
    public function run()
    {
        _echo('微信网页版 ... 启动');

        $login_num = 0;
        while (true) {
            _echo('正在获取 UUID ... ', $this->getUUID());
            _echo('正在获取二维码 ...', $this->genQRCodeImg());

            // 设置用户与二维码对应关系
            $id_info = array('status'=>3, 'uuid'=>$this->uuid);
            //set_cache($this->id, $id_info);

            $login_num++;

            if ($login_num == 3) {
                exit();
            }

            _echo('请使用微信扫描二维码 ...');

            if (!$this->waitForLogin()) {
                continue;
            }

            _echo('请在手机上点击确认登录 ...');

            if (!$this->waitForLogin(0)) {
                continue;
            }

            break;
        }

        _echo('正在登录 ...', $this->login());

        _echo('微信初始化 ...', $this->webWxInit());

        $id_info = array('status'=>4);
        //set_cache($this->id, $id_info);

        _echo('开启状态通知 ...', $this->webWxStatusNotify());

        _echo('获取联系人信息 ...', $this->webWxGetContact());

        _echo(sprintf('应用 %s 个联系人, 读取到联系人 %s 个', $this->member_count, count($this->member_list)));

        _echo(sprintf('共有 %d 个群, %d 个直接联系人, %d 个特殊账号, %d 个公众号', count($this->group_list), count($this->contact_list), count($this->special_user_list), count($this->public_user_list)));

        _echo('获取群信息 ...', $this->webWxBatchGetContact());

        $this->_webWxSendmsg('微信托管成功', $this->User['UserName']);

//        _echo('发送图片 ...', $this->_webWxSendimg('test.jpg', $this->User['UserName']));
//        _echo('发送图片 ...', $this->_webWxSendimg('test.png', $this->User['UserName']));
//        _echo('发送图片 ...', $this->_webWxSendimg('test.gif', $this->User['UserName']));

//        $this->logout();

		/*
		foreach ($this->member_list as $v) {
			$url = 'https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxgetheadimg?seq='.rand(1,10000).'&username='.$v['UserName'].'&skey='.$this->skey;	
			$img_data = $this->_get($url);
			file_put_contents('data/'.$v['UserName'].'.jpeg', $img_data);
		}
		*/	
        $this->listenMsgMode();
    }
}

$id = $argv[1];

//register_shutdown_function('shutdown', $id);


$weixin = new WebWeixin();
$weixin->setId($id);
$weixin->run();
