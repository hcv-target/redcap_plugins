#!/usr/bin/env python

from config import config
import pycurl, cStringIO, json

buf = cStringIO.StringIO()

record = {
    'arm': {
        'number': 1,
        'event': [
            {
                'unique_event_name': 'event_1_arm_1',
                'form': ['instr_1', 'instr_2',]
            },
            {
                'unique_event_name': 'event_2_arm_1',
                'form': ['instr_1',]
            },
        ]
    }
}

data = json.dumps([record])

fields = {
    'token': config['api_token'],
    'content': 'formEventMapping',
    'format': 'json',
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
