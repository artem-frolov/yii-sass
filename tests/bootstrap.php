<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii/framework/yii.php';

$basePath = sys_get_temp_dir() . '/yii-sass';
if (!is_dir($basePath)) {
    if (!mkdir($basePath)) {
        throw new Exception('Can not create directory: ' . $basePath);
    }
}
Yii::createConsoleApplication(array('basePath' => $basePath));
