<?php

require_once '../vendor/autoload.php';

$di = new \Phalcon\DI\FactoryDefault();
$di->set('memcache', function() {
    $frontCache = new \Phalcon\Cache\Frontend\Data(array(
        'lifetime' => 3600,
    ));

    return new \Phalcon\Cache\Backend\Memcache($frontCache, array(
        'host' => '127.0.0.1',
        'port' => 11211,
        'persistent' => false
    ));
});

$db = new \EugeniyPetrov\Mysql('127.0.0.1', 3306, 'root', '', 'test', 'utf8');
echo $db->one('SELECT NOW()') . "\n";

