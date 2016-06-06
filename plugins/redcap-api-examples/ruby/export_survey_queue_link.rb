#!/usr/bin/env ruby

require 'curl'
require './settings.rb'
include Settings

fields = {
  :token => Settings::API_TOKEN,
  :content => 'surveyQueueLink',
  :record => 'f21a3ffd37fc0b3c',
  :instrument => 'demographics',
  :event => 'event_1_arm_1',
  :format => 'json'
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
