#!/usr/bin/env ruby

require 'json'
require 'curl'
require './settings.rb'
include Settings

record = {
  :project_title => 'Project ABC',
  :purpose       => 0,
  :purpose_other => '',
  :project_notes => 'Some notes about the project'
}

data = record.to_json

fields = {
  :token   => Settings::API_SUPER_TOKEN,
  :content => 'project',
  :format  => 'json',
  :data    => data,
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
