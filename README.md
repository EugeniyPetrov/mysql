mysql
=====

Very simple mysql client library based on PDO with placeholders support.

Example:

    $db = new \EugeniyPetrov\Mysql('127.0.0.1', 3306, 'root', '', 'test', 'utf8');
    echo $db->one('SELECT NOW()') . "\n";
    
    print_r($db->all('SHOW TABLES LIKE :prefix', array(
        'prefix' => 'a%',
    )));
