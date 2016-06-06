#!/usr/bin/env python

from config import config
import pycurl, cStringIO

buf = cStringIO.StringIO()

fields = {
    'token': config['api_token'],
    'content': 'surveyReturnCode',
    'record': 'f21a3ffd37fc0b3c',
    'instrument': 'test_instrument',
    'event': 'event_1_arm_1',
    'format': 'json'
}

ch = pycurl.Curl()
ch.setopt(ch.URL, config['api_url'])
ch.setopt(ch.HTTPPOST, fields.items())
ch.setopt(ch.WRITEFUNCTION, buf.write)
ch.perform()
ch.close()

print buf.getvalue()
buf.close()
