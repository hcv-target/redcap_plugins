#!/usr/bin/env python

from config import config
import pycurl, cStringIO, json

buf = cStringIO.StringIO()

record = {
    'project_title': 'Project ABC',
    'purpose': 0,
    'purpose_other': '',
    'project_notes': 'Some notes about the project'
}

data = json.dumps(record)

fields = {
    'token': config['api_super_token'],
    'content': 'project',
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
