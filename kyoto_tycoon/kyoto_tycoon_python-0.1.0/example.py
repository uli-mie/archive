#!/usr/bin/env python
# -*- coding: utf-8 -*-

import kyototycoon

kt = kyototycoon.KyotoTycoon()
print 'testkey =', kt.get('testkey', 0)
print 'setting testkey=testvalue...', kt.set('testkey', 'testvalue', 0)
print 'testkey =', kt.get('testkey', 0)
print 'removing testkey...', kt.remove('testkey', 0)
print 'testkey =', kt.get('testkey', 0)

