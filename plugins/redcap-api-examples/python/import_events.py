#!/usr/bin/env python

from config import config
import pycurl, cStringIO, json

buf = cStringIO.StringIO()

record = {
    'event_name': 'Event 1',
    'arm_num': 1,
    'day_offset': 0,
    'offset_min': 0,
    'offset_max': 0,
    'unique_event_name': 'event_1_arm_1'
}

data = json.dumps([record])

fields = {
    'token': config['api_token'],
    'content': 'event',
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
