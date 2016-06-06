#!/usr/bin/env ruby

require 'curl'
require './settings.rb'
include Settings

fields = {
  :token => Settings::API_TOKEN,
  :content => 'pdf',
  :format => 'json'
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)

File.open('/tmp/export.pdf', 'wb'){|f| f.write(ch.body_str)}
