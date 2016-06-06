#!/usr/bin/env python

from config import config
import pycurl, cStringIO, json

buf = cStringIO.StringIO()

record = {
    'arm_num': 1,
    'name': 'Arm 1'
}

data = json.dumps([record])

fields = {
    'token': config['api_token'],
    'content': 'arm',
    'action': 'import',
    'format': 'json',
    'override': 0,
    'data': data,
}

ch = pycurl.Curl()
ch.setopt(ch.URL, config['api_url'])
ch.setopt(ch.HTTPPOST, fields.items())
ch.setopt(ch.WRITEFUNCTION, buf.write)
ch.perform()
ch.close()

print buf.getvalue()
buf.close()
