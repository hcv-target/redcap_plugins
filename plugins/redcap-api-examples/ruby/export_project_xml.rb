#!/usr/bin/env ruby

require 'curl'
require './settings.rb'
include Settings

fields = {
	:token => Settings::API_TOKEN,
    :content => 'project_xml',
    :returnMetadataOnly => 'false',
    :exportSurveyFields => 'false',
    :exportDataAccessGroups => 'false',
    :returnFormat => 'json'
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
