#!/usr/bin/env python
# -*- coding: utf-8 -*-

# some basic tests

MSG = '''
WARNING: Will use database ID 0 and will probably overwrite/delete your
existing data. Database 0 should be non-persistent and empty, otherwise
testcases will fail. It is recommended to use on-memory db, e.g.:
":#bnum=1000000"
tests.lua has to be added to server
'''

import kyototycoon
import unittest
import time
import math


class BasicTests(unittest.TestCase):
    db = 0

    def setUp(self):
        self.kt = kyototycoon.KyotoTycoon()
        keys = []
        vals = []
        i = 1
        lasti = -1
        while i <= 1024:
            for delta in range(max(lasti+1,i-1), i+2):
                keys += [''.join([chr((j+k*3)%256) for k in range(i+delta)])
                                               for j in range(256)]
                vals += [''.join([chr((j+k*7)%256) for k in range(i+delta)])
                                               for j in range(256)]
            lasti = i
            i *= 2
        self.recs = zip(keys,reversed(vals))
        #print 'Generated %i test records' % (len(self.recs))
    
    
    def test_set_get_remove(self):
        for flag in [0, kyototycoon.FLAG_NOREPLY]:
            for key,val in self.recs:
                ret = self.kt.get(key, self.db)
                self.assertEqual(ret, None)
            
            for key,val in self.recs:
                ret = self.kt.set(key, val, self.db, flags=flag)
                if flag == 0:
                    self.assertEqual(ret, 1)
                elif flag == kyototycoon.FLAG_NOREPLY:
                    self.assertEqual(ret, None)
            
            time.sleep(1)
            
            for key,val in self.recs:
                ret = self.kt.get(key, self.db)
                self.assertEqual(ret, val)
            
            for key,val in self.recs:
                ret = self.kt.remove(key, self.db, flags=flag)
                if flag == 0:
                    self.assertEqual(ret, 1)
                elif flag == kyototycoon.FLAG_NOREPLY:
                    self.assertEqual(ret, None)
            
            for key,val in self.recs:
                ret = self.kt.get(key, self.db)
                self.assertEqual(ret, None)
    
    
    def test_set_get_remove_bulk(self):
        cnt = 1
        while cnt <= len(self.recs):
            recs = [(key,val,self.db,kyototycoon.DEFAULT_EXPIRE) for key,val in self.recs[:cnt]]
            keydb = [(key,self.db) for key,val,db,expire in recs]
            for flag in [0, kyototycoon.FLAG_NOREPLY]:
                ret = self.kt.get_bulk(keydb)
                self.assertEqual(ret, [])
                
                ret = self.kt.set_bulk(recs, flags=flag)
                if flag == 0:
                    self.assertEqual(ret, len(recs))
                elif flag == kyototycoon.FLAG_NOREPLY:
                    self.assertEqual(ret, None)
                
                time.sleep(1)
                
                ret = self.kt.get_bulk(keydb)
                self.assertEqual(ret, recs)
                
                ret = self.kt.remove_bulk(keydb, flags=flag)
                if flag == 0:
                    self.assertEqual(ret, len(recs))
                elif flag == kyototycoon.FLAG_NOREPLY:
                    self.assertEqual(ret, None)
                
                ret = self.kt.get_bulk(keydb)
                self.assertEqual(ret, [])
            cnt *= 2
    
    def test_set_get_remove_kv(self):
        cnt = 1
        while cnt <= len(self.recs):
            recsdict = dict(self.recs[:cnt])
            for flag in [0, kyototycoon.FLAG_NOREPLY]:
                ret = self.kt.get_bulk_keys(recsdict.keys(), self.db)
                self.assertEqual(ret, {})
                
                ret = self.kt.set_bulk_kv(recsdict, self.db, flags=flag)
                if flag == 0:
                    self.assertEqual(ret, len(recsdict))
                elif flag == kyototycoon.FLAG_NOREPLY:
                    self.assertEqual(ret, None)
                
                time.sleep(1)
                
                ret = self.kt.get_bulk_keys(recsdict.keys(), self.db)
                self.assertEqual(ret, recsdict)
                
                ret = self.kt.remove_bulk_keys(recsdict.keys(), self.db, flags=flag)
                if flag == 0:
                    self.assertEqual(ret, len(recsdict))
                elif flag == kyototycoon.FLAG_NOREPLY:
                    self.assertEqual(ret, None)
                
                ret = self.kt.get_bulk_keys(recsdict.keys(), self.db)
                self.assertEqual(ret, {})
            cnt *= 2


class ExpireTests(unittest.TestCase):
    db = 0

    def setUp(self):
        self.kt = kyototycoon.KyotoTycoon()
        self.expire_tests = [2,5]
        self.recs = [(chr(i),chr((i+127)%256)) for i in range(256)]
        #print 'Generated %i test records for %i different expiration times' % (len(self.recs), len(self.expire_tests))
    
    
    def test_set_get(self):
        for expire_test in self.expire_tests:
            texpire = math.ceil(time.time())+expire_test
            
            for key,val in self.recs:
                ret = self.kt.get(key, self.db)
                self.assertEqual(ret, None)
            
            for key,val in self.recs:
                ret = self.kt.set(key, val, self.db, expire=-texpire)
                self.assertEqual(ret, 1)
            
            for key,val in self.recs:
                ret = self.kt.get(key, self.db)
                self.assertEqual(ret, val)
            
            time.sleep(texpire-math.floor(time.time())+1)
            
            for key,val in self.recs:
                ret = self.kt.get(key, self.db)
                self.assertEqual(ret, None)
    
    
    def test_set_get_bulk(self):
        for expire_test in self.expire_tests:
            texpire = math.ceil(time.time())+expire_test
            
            recs = [(key,val,self.db,-texpire) for key,val in self.recs]
            keydb = [(key,self.db) for key,val,db,expire in recs]
        
            ret = self.kt.get_bulk(keydb)
            self.assertEqual(ret, [])
            
            ret = self.kt.set_bulk(recs)
            self.assertEqual(ret, len(recs))
            
            ret = self.kt.get_bulk(keydb)
            self.assertEqual(ret, [(key,val,db,abs(expire)) for key,val,db,expire in recs])
            
            time.sleep(texpire-math.floor(time.time())+1)
            
            ret = self.kt.get_bulk(keydb)
            self.assertEqual(ret, [])
    
    def test_set_get_kv(self):
        for expire_test in self.expire_tests:
            texpire = math.ceil(time.time())+expire_test
            
            recsdict = dict(self.recs)
            ret = self.kt.get_bulk_keys(recsdict.keys(), self.db)
            self.assertEqual(ret, {})
            
            ret = self.kt.set_bulk_kv(recsdict, self.db, expire=-texpire)
            self.assertEqual(ret, len(recsdict))
            
            ret = self.kt.get_bulk_keys(recsdict.keys(), self.db)
            self.assertEqual(ret, recsdict)
            
            time.sleep(texpire-math.floor(time.time())+1)
            
            ret = self.kt.get_bulk_keys(recsdict.keys(), self.db)
            self.assertEqual(ret, {})


class LuaTests(unittest.TestCase):
    
    def setUp(self):
        self.kt = kyototycoon.KyotoTycoon()
    
    
    def test_lua1(self):
        cnts = [0] + [2**i for i in range(10)]
        for cnt in cnts:
            keys = [chr(i%256)*i+'k' for i in range(cnt)]
            vals = [chr((i+1)%256)*(cnt-i) for i in range(cnt)]
            ret = self.kt.play_script('test1', zip(keys,vals))
            self.assertEqual(set(ret), set(zip(['outk'+key for key in keys], ['outv'+val for val in vals])))
    
    
    def test_lua234(self):
        self.assertRaises(kyototycoon.KyotoTycoonError, self.kt.play_script, 'test2', [])
        self.assertRaises(kyototycoon.KyotoTycoonError, self.kt.play_script, 'test3', [])
        self.assertRaises(kyototycoon.KyotoTycoonError, self.kt.play_script, 'test4', [])


if __name__ == '__main__':
    print MSG
    if raw_input('continue? (y/n) ') == 'y':
        print 'Please wait'
        unittest.main()
    else:
        'tests aborted'

