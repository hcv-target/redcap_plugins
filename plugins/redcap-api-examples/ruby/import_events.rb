#!/usr/bin/env ruby

require 'json'
require 'curl'
require './settings.rb'
include Settings

record = {
  :event_name => 'Event 1',
  :arm_num => 1,
  :day_offset => 0,
  :offset_min => 0,
  :offset_max => 0,
  :unique_event_name => 'event_1_arm_1'
}

data = [record].to_json

fields = {
  :token    => Settings::API_TOKEN,
  :content  => 'event',
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
