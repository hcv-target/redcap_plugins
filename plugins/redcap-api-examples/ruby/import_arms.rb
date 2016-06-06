#!/usr/bin/env ruby

require 'json'
require 'curl'
require './settings.rb'
include Settings

record = {
  :arm_num => 1,
  :name => 'Arm 1'
}

data = [record].to_json

fields = {
  :token    => Settings::API_TOKEN,
  :content  => 'arm',
  :action   => 'import',
  :format   => 'json',
  :override => 0,
  :data     => data,
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
