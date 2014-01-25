<?php

require_once '../vendor/autoload.php';

$db = new \EugeniyPetrov\Mysql('127.0.0.1', 'root', '', 'test', 'utf8');
echo $db->one('SELECT NOW()') . "\n";