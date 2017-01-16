<?php


function shutdown($id)
{
    $id_info = array('status'=>0);
    set_cache($id, $id_info);

    $online_list = get_cache('online_list');

    empty($online_list) && $online_list = array();

    unset($online_list[array_search($id, $online_list)]);
    set_cache('online_list', array_unique($online_list));

    _echo('进程退出');
}

function _echo($content, $status=0)
{
    echo '[*] '.date('Y-m-d H:i:s').' '.$content;

    if ($status === true)
        echo ' 成功';

    if ($status === false) {
        echo ' 失败';
        echo " \n";
        exit();
    }

    echo " \n";
}

function _save_data($data, $id)
{
    $data['time'] = date('Y-m-d H:i:s');
    file_put_contents('data/'.$id.'.data', json_encode($data, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
}



/**
 * 获取缓存
 * @param $key
 * @return mixed
 */
function get_cache($key)
{
    $mc = new Memcached();
    $mc->addServer("localhost", 11211);

    return $mc->get($key);
}


/**
 * 设置缓存
 * @param $key
 * @param $value
 * @param int $expiration
 * @return bool
 */
function set_cache($key, $value, $expiration=3600)
{
    $mc = new Memcached();
    $mc->addServer("localhost", 11211);

    return $mc->set($key, $value, $expiration);
}

/**
 * 删除缓存
 * @param $key
 */
function del_cache($key)
{
    $m = new Memcached();
    $m->addServer('localhost', 11211);

    $m->delete($key);
}