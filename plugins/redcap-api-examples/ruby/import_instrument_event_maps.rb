#!/usr/bin/env ruby

require 'json'
require 'curl'
require './settings.rb'
include Settings

record = {
  :arm => {
    :number => 1,
    :event => [
      {
        :unique_event_name => 'event_1_arm_1',
        :form => ['instr_1', 'instr_2',]
      },
      {
        :unique_event_name => 'event_2_arm_1',
        :form => ['instr_1',]
      },
    ]
  }
}

data = [record].to_json

fields = {
  :token   => Settings::API_TOKEN,
  :content => 'formEventMapping',
  :format  => 'json',
  :data    => data,
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
