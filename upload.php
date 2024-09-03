<?php

/*
 * PHP upload demo for Editor.md
 *
 * @FileName: upload.php
 * @Auther: Pandao
 * @E-mail: pandao@vip.qq.com
 * @CreateTime: 2015-02-13 23:20:04
 * @UpdateTime: 2015-02-14 14:52:50
 * Copyright@2015 Editor.md all right reserved.
 */

//header("Content-Type:application/json; charset=utf-8"); // Unsupport IE
header("Content-Type:application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require("editormd.uploader.class.php");


if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__) . '/../../..');
}

require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
require_once __TYPECHO_ROOT_DIR__ . '/var/Utils/Helper.php';

error_reporting(E_ALL & ~E_NOTICE);

$base_dir = dirname(__FILE__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
if (!is_dir($base_dir)){
    mkdir($base_dir);
}
$date_dir = date('Ymd');
$savePath = $base_dir . $date_dir . DIRECTORY_SEPARATOR;
if (!is_dir($savePath)) {
    mkdir($savePath);
}
$saveURL = '/usr/uploads/' . $date_dir . '/';


$formats = array(
    'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'webp')
);

$name = 'editormd-image-file';

if (isset($_FILES[$name])) {
    $imageUploader = new EditorMdUploader($savePath, $saveURL, $formats['image'], true, 'His');  // YmdHis表示按日期生成文件名，利用date()函数

    $imageUploader->config(array(
        'maxSize' => 10240 * 3,  // 30MB
        'cover' => false         // 是否覆盖同名文件，默认为true
    ));

    if ($imageUploader->upload($name)) {
        // $imageUploader->message('上传成功！', 1);
        // $imageUploader->message($_FILES[$name]["size"], 0);
        upload2S3($imageUploader);
    } else {
        $imageUploader->message('上传失败！', 0);
    }
}

function upload2S3($imageUploader) {
    $s3ConfigString = trim(\Utils\Helper::options()->plugin('FreewindMarkdown')->s3_config);
    if (empty($s3ConfigString)) {
        $imageUploader->message('上传服务器成功！', 1);
        return;
    }
    $s3ConfigArray = explode('|', $s3ConfigString);
    if (count($s3ConfigArray) !== 4) {
        $imageUploader->message('上传服务器成功但s3配置错误！', 1);
        return;
    }
    require_once './lib/aws/aws-autoloader.php';
    $endpoint = $s3ConfigArray[0];
    $bucketName = $s3ConfigArray[1];
    $accessKeyId = $s3ConfigArray[2];
    $accessKeySecret = $s3ConfigArray[3];
    $credentials = new Aws\Credentials\Credentials($accessKeyId, $accessKeySecret);

    $options = [
        'region' => 'auto',
        'endpoint' => $endpoint,
        'version' => 'latest',
        'credentials' => $credentials
    ];

    $s3_client = new Aws\S3\S3Client($options);
    // $contents = $s3_client->listObjectsV2([
    //     'Bucket' => $bucketName
    // ]);
    $wholeFilePath = $imageUploader->savePath . $imageUploader->saveName;
    try {
        $result = $s3_client->putObject([
            'Bucket' => $bucketName,
            'Key'    => date('Ymd') . '/' . basename($wholeFilePath),
            'Body'   => fopen($wholeFilePath, 'r'),
            'ACL'    => 'public-read',
        ]);
        $imageUploader->message('上传服务器成功且同步至S3成功！文件地址：' . $result->get('ObjectURL') , 1);
    } catch (Aws\S3\Exception\S3Exception $e) {
        $imageUploader->message('上传服务器成功但同步至S3失败！', 0);
    }
}
?>
