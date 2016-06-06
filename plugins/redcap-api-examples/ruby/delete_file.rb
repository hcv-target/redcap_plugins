#!/usr/bin/env ruby

require 'curl'
require './settings.rb'
include Settings

fields = {
  :token => Settings::API_TOKEN,
  :content => 'file',
  :action => 'delete',
  :record => 'f21a3ffd37fc0b3c',
  :field => 'file_upload',
  :event => 'event_1_arm_1',
}

fields[:returnFormat] = 'json'

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
