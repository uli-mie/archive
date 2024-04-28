<?php

require('KyotoTycoon.php');

$kt = KyotoTycoon::get_connection();
echo 'testkey = ';
var_dump($kt->get('testkey', 0));
echo 'setting testkey=testvalue... ';
var_dump($kt->set('testkey', 'testvalue', 0));
echo 'testkey = ';
var_dump($kt->get('testkey', 0));
echo 'removing testkey... ';
var_dump($kt->remove('testkey', 0));
echo 'testkey = ';
var_dump($kt->get('testkey', 0));

?>
