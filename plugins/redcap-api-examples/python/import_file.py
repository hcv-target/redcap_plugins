#!/usr/bin/env python

from config import config
import pycurl, cStringIO

buf = cStringIO.StringIO()

file = '/tmp/test_file.txt'

fields = {
    'token': config['api_token'],
    'content': 'file',
    'action': 'import',
    'record': 'f21a3ffd37fc0b3c',
    'field': 'file_upload',
    'event': 'event_1_arm_1',
    'file': (pycurl.FORM_FILE, file)
}

fields['returnFormat'] = 'json';

ch = pycurl.Curl()
ch.setopt(ch.URL, config['api_url'])
ch.setopt(ch.HTTPPOST, fields.items())
ch.setopt(ch.WRITEFUNCTION, buf.write)
ch.perform()
ch.close()

print buf.getvalue()
buf.close()
