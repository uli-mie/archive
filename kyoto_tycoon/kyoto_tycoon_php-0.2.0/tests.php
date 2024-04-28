<?php
// Performs some simple checks if implementation is correct, you should run this before using the
// library in production, but BE CARÈFUL: It will overwrite contents in your DB, so start KT with
// an empty temporary database!

/*

Copyright (c) 2011-2013 Ulrich Mierendorff

Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.

*/

require('KyotoTycoon.php');

function test_pack64()
{
    echo "Testing _unpack_int64, _pack_int64";
    
    $class = new ReflectionClass('KyotoTycoon');
    $_pack_int64 = $class->getMethod('_pack_int64');
    $_pack_int64->setAccessible(TRUE);
    $_unpack_int64 = $class->getMethod('_unpack_int64');
    $_unpack_int64->setAccessible(TRUE);
    
    $ints = explode("\n", file_get_contents('num.dat'));
    $bins = str_split(file_get_contents('bin.dat'), 8);
    $cnt = count($bins);

    for ($i = 0; $i < $cnt; ++$i)
    {
        $ints[$i] = (int) $ints[$i];
        $bin = $_pack_int64->invokeArgs(NULL, array($ints[$i]));
        $int = $_unpack_int64->invokeArgs(NULL, array($bins[$i]));
        if ($int !== $ints[$i] || $bin !== $bins[$i])
        {
            $binshex = bin2hex($bins[$i]);
            $binhex = bin2hex($bin);
            throw new Exception("\nERROR: input: ${ints[$i]} - $binshex (output: $int - $binhex)");
        }
    }
    echo "  -  all $cnt pack-tests passed\n";
}

test_pack64();

$kt = KyotoTycoon::get_connection();
$db = 0;
$kv = array();
for ($i = 1; $i <= 1048576; $i *= 2)
{
    $key = (string) $i;
    $val = sha1(chr(mt_rand(0, 255)), TRUE);
    $val = str_repeat($val, (int) ceil($i/20.0));
    $val = substr($val, 0, $i);
    $kv[$key] = $val;
}

foreach ($kv as $key=>$val)
{
    if ($kt->get($key, $db) !== NULL)
    {
        throw new Exception('ERROR get kv already existed!');
    }
    if ($kt->remove($key, $db) !== 0)
    {
        throw new Exception('ERROR removing not existing kv!');
    }
    if ($kt->remove($key, $db, KyotoTycoon::FLAG_NOREPLY) !== NULL)
    {
        throw new Exception('ERROR removing not existing kv with flag_noreply!');
    }
}
if (count($kt->get_bulk_keys(array_keys($kv), $db)) !== 0)
{
    throw new Exception('ERROR bulk get kv already existed!');
}
if ($kt->remove_bulk_keys(array_keys($kv), $db) !== 0)
{
    throw new Exception('ERROR bulk remove!');
}
if ($kt->remove_bulk_keys(array_keys($kv), $db, KyotoTycoon::FLAG_NOREPLY) !== NULL)
{
    throw new Exception('ERROR bulk remove with flag_noreply!');
}

foreach ($kv as $key=>$val)
{
    if ($kt->set($key, $val, $db) !== 1)
    {
        throw new Exception('ERROR set!');
    }
    if ($kt->get($key, $db) !== $val)
    {
        throw new Exception('ERROR get, values do not match!');
    }
    if ($kt->remove($key, $db) !== 1)
    {
        throw new Exception('ERROR removing single key!');
    }
    if ($kt->get($key, $db) !== NULL)
    {
        throw new Exception('ERROR get, value still exists!');
    }
    
    if ($kt->set($key, $val, $db, 2) !== 1)
    {
        throw new Exception('ERROR set with expire!');
    }
    if ($kt->get($key, $db) !== $val)
    {
        throw new Exception('ERROR get with expire, values do not match!');
    }
    sleep(3);
    if ($kt->get($key, $db) !== NULL)
    {
        throw new Exception('ERROR get with expire, values still exists!');
    }
}

if ($kt->set_bulk_kv($kv, $db) !== count($kv))
{
    throw new Exception('ERROR set_bulk_kv!');
} 
if (count(array_diff_assoc($kt->get_bulk_keys(array_keys($kv), $db), $kv)) !== 0)
{
    throw new Exception('ERROR get_bulk_kv, values differ!');
}
if ($kt->remove_bulk_keys(array_keys($kv), $db) !== count($kv))
{
    throw new Exception('ERROR remove_bulk_keys!');
}

if ($kt->set_bulk_kv($kv, $db, 2) !== count($kv))
{
    throw new Exception('ERROR set_bulk_kv with expire!');
} 
if (count(array_diff_assoc($kt->get_bulk_keys(array_keys($kv), $db), $kv)) !== 0)
{
    throw new Exception('ERROR get_bulk_keys with expire, values differ!');
}
sleep(3);
if (count($kt->get_bulk_keys(array_keys($kv), $db)) !== 0)
{
    throw new Exception('ERROR get_bulk_keys with expire, values still exist!');
}

foreach (array(0,1,2,4,8,16,32,64,128,256,512) as $cnt) {
    $lua_in = array();
    $lua_out = array();
    for ($j = 0; $j < $cnt; ++$j) {
        $lua_in[$j]['key'] = str_repeat(chr($j%256), $j) . 'k';
        $lua_in[$j]['val'] = str_repeat(chr(($j+1)%256), $cnt-$j);
        $lua_out[$j]['key'] = 'outk' . $lua_in[$j]['key'];
        $lua_out[$j]['val'] = 'outv' . $lua_in[$j]['val'];
    }
    $ret = $kt->play_script('test1', $lua_in);
    if (count(array_diff_assoc($ret, $lua_out)) !== 0) {
        throw new Exception('ERROR play_script, return values not as expected!');
    }
}
echo "ALL tests passed\n";
?>
