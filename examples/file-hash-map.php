<?php

use PhpFileHashMap\FileStorageAdapter;
use PhpFileHashMap\FileHashMap;

require_once '../vendor/autoload.php';


$fileStorageAdapter = new FileStorageAdapter('/tmp/file.hash', 1000000);
$hashMap = new FileHashMap($fileStorageAdapter, 1000000);

$hashMap->set('key1', 'value1');
$hashMap->set('key2', 'value2');
$hashMap->set('key3', 'value3');

echo 'READING' . PHP_EOL;

echo sprintf('Key: %s, Value: %s' . PHP_EOL, 'key1', $hashMap->get('key1'));
echo sprintf('Key: %s, Value: %s' . PHP_EOL, 'key2', $hashMap->get('key2'));
echo sprintf('Key: %s, Value: %s' . PHP_EOL, 'key3', $hashMap->get('key3'));

//$hashMap->remove('key1');
//echo sprintf('Key: %s, Value: %s' . PHP_EOL, 'key1', $hashMap->get('key1'));

try {
    $hashMap->get('key4');
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

echo sprintf('Has key: %s, has: %s' . PHP_EOL, 'key1', $hashMap->has('key1'));
echo sprintf('Has key: %s, has: %s' . PHP_EOL, 'key4', $hashMap->has('key4'));


$hashMap->set('key5', new \stdClass());
echo sprintf('Key: %s, Value: %s' . PHP_EOL, 'key5', print_r($hashMap->get('key5'), true));

echo PHP_EOL;
echo PHP_EOL;
echo PHP_EOL;

/*
for ($i = 0; $i < 1000; $i++) {
    $bigArray = [];
    for ($j = 0; $j < 1000; $j++) {
        $bigArray[] = 'test' . $i;
    }

    $key = 'bigArray' . $i;
    $hashMap->set($key, $bigArray);
    //echo sprintf('Key: %s, Value: %s' . PHP_EOL, $key, print_r($hashMap->get($key), true));
}
*/
$hashMap->set('bigArray', 1);
for ($i = 0; $i < 500000; $i++) {
    //$data[$i] = $i;
    //$data[$i];

    $hashMap->get('bigArray');
    //$hashMap->set('bigArray' . $i, 1);
    //echo $i . PHP_EOL;
}

/*
$data = [];
$hashMap->set('bigArray', 1);
for ($i = 0; $i < 130000; $i++) {
    //$data[$i] = $i;
    //$data[$i];

    //$hashMap->get('bigArray');
    $hashMap->set('bigArray' . $i, 1);
    //echo $i . PHP_EOL;
}
*/


