<?php

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

class KyotoTycoon_Exception extends Exception {}

class KyotoTycoon
{  
    const MB_SET_BULK = 0xb8;
    const MB_GET_BULK = 0xba;
    const MB_REMOVE_BULK = 0xb9;
    const MB_ERROR = 0xbf;
    const MB_PLAY_SCRIPT = 0xb4;
    
    const DEFAULT_HOST = "localhost";
    const DEFAULT_PORT = 1978;
    public static $DEFAULT_EXPIRE = 0; /* see bottom of file for actual setting */
    const FLAG_NOREPLY = 0x01;

    private $socket = FALSE;
    private $host = NULL;
    private $port = NULL;
    private $timeout = NULL;
    
    private static $connections = array();
        
    public static function get_connection($host=self::DEFAULT_HOST, $port=self::DEFAULT_PORT,
                                $lazy=TRUE, $connect_timeout=NULL, $timeout=NULL)
    {
        $uniqid = implode('#', array($host, $port, $lazy, $connect_timeout, $timeout));
        if (!isset(self::$connections[$uniqid]))
        {
            self::$connections[$uniqid] = new KyotoTycoon($host, $port, $lazy, $connect_timeout, $timeout);
        }
        
        return self::$connections[$uniqid];
    }
    
    private final function __clone()
    {
    }
    
    private function __construct($host, $port, $lazy, $connect_timeout, $timeout)
    {
        if (!is_string($host))
        {
            throw new KyotoTycoon_Exception('String expected for parameter $host');
        }
        $this->host = $host;
        
        if (!is_int($port))
        {
            throw new KyotoTycoon_Exception('Integer expected for parameter $port');
        }
        $this->port = $port;
        
        if ($connect_timeout !== NULL && (!is_int($connect_timeout) || $connect_timeout < 0))
        {
            throw new KyotoTycoon_Exception('NULL or integer >= 0 expected for parameter $connect_timeout');
        }
        $this->connect_timeout = $connect_timeout;
        
        if ($timeout !== NULL && (!is_int($timeout) || $timeout < 0))
        {
            throw new KyotoTycoon_Exception('NULL or integer >= expected for parameter $timeout');
        }
        $this->timeout = $timeout;
        
        
        if ($lazy === FALSE)
        {
            $this->_connect();
        }
    }
    
    
    
    public function __destruct()
    {
        $this->_close();
    }
    
    
    
    public function get_metadata()
    {
        if ($this->socket === FALSE)
        {
            throw new KyotoTycoon_Exception('Cannot get metadata (not connected to server)');
        }
        
        return stream_get_meta_data($this->socket);
    }
    
    
    
    public function set($key, $val, $db, $expire=NULL, $flags=0)
    {
        if ($expire === NULL) {
            $expire = self::$DEFAULT_EXPIRE;
        }
        return $this->set_bulk(array(array('key'=>$key,
                                           'val'=>$val,
                                           'expire'=>$expire,
                                           'db'=>$db)), $flags);
    }
    
    
    
    public function set_bulk_kv($kv, $db, $expire=NULL, $flags=0)
    {
        if ($expire === NULL) {
            $expire = self::$DEFAULT_EXPIRE;
        }
        $recs = array();
        $i = 0;
        foreach ($kv as $key=>$value)
        {
            $recs[$i] = array('key'=>$key, 'val'=>$value, 'expire'=>$expire, 'db'=>$db);
            ++$i;
        }
        
        return $this->set_bulk($recs, $flags);
    }
    
    
    
    public function set_bulk($recs, $flags=0)
    {      
        if ($this->socket === FALSE)
        {
            $this->_connect();
        }
        
        $recs_cnt = count($recs);
        
        $request = pack('CNN', self::MB_SET_BULK, $flags, $recs_cnt);
        
        for ($i = 0; $i < $recs_cnt; ++$i)
        {
            $key = $recs[$i]['key'];
            $val = $recs[$i]['val'];
            $db = $recs[$i]['db'];     
            $expire = $recs[$i]['expire'];
            
            $request .= pack('nNN', $db, strlen($key), strlen($val));
            $request .= self::_pack_int64($expire);
            $request .= $key;
            $request .= $val;
        }
        
        $this->_write($request);
        
        if ($flags & self::FLAG_NOREPLY)
        {
            return NULL;
        }
        else
        {
            list(,$magic) = unpack('C', $this->_read(1));
            if ($magic === self::MB_SET_BULK)
            {
                list(,$recs_cnt) = unpack('N', $this->_read(4));
                return $recs_cnt;
            }
            else if ($magic === self::MB_ERROR)
            {
                throw new KyotoTycoon_Exception('Internal server error '.self::MB_ERROR);
            }
            else
            {
                throw new KyotoTycoon_Exception('Unknown server error');
            }
        }
    }
    
    
    
    public function get($key, $db, $flags=0)
    {
        $recs = $this->get_bulk(array(array('key'=>$key, 'db'=>$db)), $flags);
        if (count($recs) === 0)
        {
            return NULL;
        }
        
        return $recs[0]['val'];
    }
    
    
    
    public function get_bulk_keys($keys, $db, $flags=0)
    {
        $recs = array();
        $keys_cnt = count($keys);
        for ($i = 0; $i < $keys_cnt; ++$i)
        {
            $recs[$i] = array('key'=>$keys[$i], 'db'=>$db);
        }
        
        $recs = $this->get_bulk($recs, $flags);
        $_recs = array();
        for ($i = 0, $ii = count($recs); $i < $ii; ++$i)
        {
            $_recs[$recs[$i]['key']] = $recs[$i]['val'];
            unset($recs[$i]); // saves some memory
        }
        
        return $_recs;
    }
    
    
    
    public function get_bulk($recs, $flags=0)
    {
        if ($this->socket === FALSE)
        {
            $this->_connect();
        }
        
        $recs_cnt = count($recs);
        
        $request = pack('CNN', self::MB_GET_BULK, $flags, $recs_cnt);
        
        for ($i = 0; $i < $recs_cnt; ++$i)
        {        
            $key = $recs[$i]['key'];
            $db = $recs[$i]['db'];
            
            $request .= pack('nN', $db, strlen($key));
            $request .= $key;
        }
        
        $this->_write($request);
        
        list(,$magic) = unpack('C', $this->_read(1));
        
        if ($magic === self::MB_GET_BULK)
        {
            list(,$recs_cnt) = unpack('N', $this->_read(4));
            
            $recs = array();
            for ($i = 0; $i < $recs_cnt; ++$i)
            {
                list(,$db) = unpack('n', $this->_read(2));
                list(,$key_len) = unpack('N', $this->_read(4));
                list(,$val_len) = unpack('N', $this->_read(4));
                $expire = self::_unpack_int64($this->_read(8));
                $key = $this->_read($key_len);
                $val = $this->_read($val_len);
                
                $recs[$i] = array('key'=>$key, 'val'=>$val, 'db'=>$db, 'expire'=>$expire);
            }
            
            return $recs;
        }
        else if ($magic === self::MB_ERROR)
        {
            throw new KyotoTycoon_Exception('Internal server error '.self::MB_ERROR);
        }
        else
        {
            throw new KyotoTycoon_Exception('Unknown server error');
        }
    }
    
    
    
    public function remove($key, $db, $flags=0)
    {
        return $this->remove_bulk(array(array('key'=>$key, 'db'=>$db)), $flags);
    }
    
    
    
    public function remove_bulk_keys($keys, $db, $flags=0)
    {
        $recs = array();
        $keys_cnt = count($keys);
        for ($i = 0; $i < $keys_cnt; ++$i)
        {
            $recs[$i] = array('key'=>$keys[$i], 'db'=>$db);
        }
        
        return $this->remove_bulk($recs, $flags);
    }
    
    
    
    public function remove_bulk($recs, $flags=0)
    {
        if ($this->socket === FALSE)
        {
            $this->_connect();
        }
        
        $recs_cnt = count($recs);
        
        $request = pack('CNN', self::MB_REMOVE_BULK, $flags, $recs_cnt);
        
        for ($i = 0; $i < $recs_cnt; ++$i)
        {        
            $key = $recs[$i]['key'];
            $db = $recs[$i]['db'];
            
            $request .= pack('nN', $db, strlen($key));
            $request .= $key;
        }
        
        $this->_write($request);
        
        if ($flags & self::FLAG_NOREPLY)
        {
            return NULL;
        }
        else
        {
            list(,$magic) = unpack('C', $this->_read(1));
            if ($magic === self::MB_REMOVE_BULK)
            {
                list(,$recs_cnt) = unpack('N', $this->_read(4));
                return $recs_cnt;
            }
            else if ($magic === self::MB_ERROR)
            {
                throw new KyotoTycoon_Exception('Internal server error '.self::MB_ERROR);
            }
            else
            {
                throw new KyotoTycoon_Exception('Unknown server error');
            }
        }
    }
    
    
    
    public function play_script($name, $recs, $flags=0)
    {
        if ($this->socket === FALSE)
        {
            $this->_connect();
        }
        
        $recs_cnt = count($recs);
        
        $request = pack('CNNN', self::MB_PLAY_SCRIPT, $flags, strlen($name), $recs_cnt);
        $request .= $name;
        
        for ($i = 0; $i < $recs_cnt; ++$i)
        {
            $key = $recs[$i]['key'];
            $val = $recs[$i]['val'];
            
            $request .= pack('NN', strlen($key), strlen($val));
            $request .= $key;
            $request .= $val;
        }
        
        $this->_write($request);
        
        if ($flags & self::FLAG_NOREPLY)
        {
            return NULL;
        }
        else
        {
            list(,$magic) = unpack('C', $this->_read(1));
            
            if ($magic === self::MB_PLAY_SCRIPT)
            {
                list(,$recs_cnt) = unpack('N', $this->_read(4));
                
                $recs = array();
                for ($i = 0; $i < $recs_cnt; ++$i)
                {
                    list(,$key_len) = unpack('N', $this->_read(4));
                    list(,$val_len) = unpack('N', $this->_read(4));
                    $key = $this->_read($key_len);
                    $val = $this->_read($val_len);
                    
                    $recs[$i] = array('key'=>$key, 'val'=>$val);
                }
                
                return $recs;
            }
            else if ($magic === self::MB_ERROR)
            {
                throw new KyotoTycoon_Exception('Internal server error '.self::MB_ERROR);
            }
            else
            {
                throw new KyotoTycoon_Exception('Unknown server error');
            }
        }
    }
    
    
    
    private function _connect()
    {
        if ($this->connect_timeout === NULL)
        {
            $this->socket = fsockopen($this->host, $this->port, $errno, $errstr);
        }
        else
        {
            $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->connect_timeout);
        }
        if ($this->socket === FALSE)
        {
            throw new KyotoTycoon_Exception('Connection failed ('.$errno.' '.$errstr.')');
        }
        
        if ($this->timeout !== NULL)
        {
            if (!stream_set_timeout($this->socket, $this->timeout))
            {
                $this->_close();
                throw new KyotoTycoon_Exception('Could not set timeout for connection. Connection closed.');
            }
        }
    }
    
    
    
    private function _close()
    {
        if ($this->socket !== FALSE)
        {
            fclose($this->socket);
            $this->socket = FALSE;
        }
    }
    
    
    
    private function _write($str)
    {
        $strlen = strlen($str);
        $written = 0;
        $tmp = 0;
        
        while ($written < $strlen && ($tmp = fwrite($this->socket, substr($str, $written))) !== FALSE)
        {
            /*TODO: this should be replaced by a better solution, also see: 
              http://www.php.net/manual/function.fwrite.php#96951 */
            if ($tmp === 0)
            {
                $tmp = FALSE;
                break;
            }
            $written += $tmp;
        }
        if ($tmp === FALSE)
        {
            throw new KyotoTycoon_Exception('Could not send data to server.');
        }
    }
    
    
    
    private function _read($strlen)
    {
        $str = stream_get_contents($this->socket, $strlen);
        if ($str === FALSE)
        {
            $info = stream_get_meta_data($this->socket);
            if ($info['timed_out'])
            {
                throw new KyotoTycoon_Exception('Connection to server timed out.');
            }
            else
            {
                throw new KyotoTycoon_Exception('Error while receiving data from server.');
            }
        }
        return $str;
    }
    

   
    private static function _unpack_int64($str)
    {
        if ($str === "\0\0\0\0\0\0\0\0")
        {
            return 0;
        }

        if (PHP_INT_SIZE >= 8) {
            list($hi1, $hi2, $lo1, $lo2) = array_values(unpack('n*', $str));
            return ($hi1 << 48) + ($hi2 << 32) + ($lo1 << 16) + $lo2;
            /*list($hi, $lo) = array_values(unpack('n*', $str));
            return ($hi << 32) + $lo;*/
        } else if (PHP_INT_SIZE >= 4) {
            list($hi1, $hi2, $lo1, $lo2) = array_values(unpack('n*', $str));
            if ($hi1 !== 0 || $hi2 !== 0) {
                throw new KyotoTycoon_Exception('expiration time > 0x7fffffff not supported on <64bit machine');
            }
            return ($lo1 << 16) + $lo2;
        }
        
        
        throw new KyotoTycoon_Exception('expiration time != 0 not supported on <32bit machine');
    }
    
    
    
    private static function _pack_int64($int)
    {
        if (!is_int($int))
        {
            throw new KyotoTycoon_Exception('Integer expected');
        }
        
        if ($int === 0)
        {
            return "\0\0\0\0\0\0\0\0";
        }

        if (PHP_INT_SIZE >= 8) {
            return pack('NN', ($int >> 32) & 0xffffffff, $int & 0xffffffff);
        } else if (PHP_INT_SIZE >= 4) {
            return "\0\0\0\0" . pack('N', $int);
        }
        
        
        throw new KyotoTycoon_Exception('expiration time != 0 not supported on <32bit machine');
    }
}

/* is it possible to make DEFAULT_EXPIRE a constant? */
if (PHP_INT_SIZE >= 8) {
    KyotoTycoon::$DEFAULT_EXPIRE = 0xffffffffff;
} else if (PHP_INT_SIZE >= 4) {
    KyotoTycoon::$DEFAULT_EXPIRE = 0x7fffffff;
}
?>
