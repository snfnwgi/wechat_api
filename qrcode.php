<?php

require 'libs/function.php';

$id = $_GET['id'];

// 检查进程状态
$id_info = get_cache($id);

// 添加到生成进程的队列中
$process_list = get_cache('process_list');

if ($process_list == false) {
    $process_list = array();
}

// 没有生成进程
if (!$id_info) {
    $process_list[] = $id;

    // 加入到生成进程队列中
    set_cache('process_list', array_unique($process_list));

    // 进程状态为未创建
    $id_info = array('status'=>1);
    set_cache($id, $id_info);
}


$online_list = get_cache('online_list');

$id_info['online_list_count'] = count($online_list);
$id_info['wait_list_count'] = count($process_list);

// 已生成二维码
if (isset($id_info['status'])) {
    echo json_encode($id_info);
} else {
    echo json_encode(array('status'=>0));
}