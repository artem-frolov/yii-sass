<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii/framework/yii.php';
require __DIR__ . '/../vendor/yiisoft/yii/framework/console/CConsoleApplication.php';
require __DIR__ . '/../vendor/yiisoft/yii/framework/console/CConsoleCommandRunner.php';
require __DIR__ . '/../vendor/yiisoft/yii/framework/base/CApplicationComponent.php';
require __DIR__ . '/../vendor/yiisoft/yii/framework/utils/CFileHelper.php';
require __DIR__ . '/../vendor/yiisoft/yii/framework/logging/CLogger.php';

spl_autoload_unregister(array('YiiBase','autoload'));

$basePath = sys_get_temp_dir() . '/yii-sass';
if (!is_dir($basePath)) {
    if (!mkdir($basePath)) {
        throw new Exception('Can not create directory: ' . $basePath);
    }
}
Yii::createConsoleApplication(array('basePath' => $basePath));
