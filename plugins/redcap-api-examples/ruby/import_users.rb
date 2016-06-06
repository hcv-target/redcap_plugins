#!/usr/bin/env ruby

require 'json'
require 'curl'
require './settings.rb'
include Settings

record = {
  :username                 => 'test_user_47',
  :expiration               => '2016-01-01',
  :data_access_group        => 1,
  :data_export              => 1,
  :mobile_app               => 1,
  :mobile_app_download_data => 1,
  :lock_record_multiform    => 1,
  :lock_record              => 1,
  :lock_record_customize    => 1,
  :record_delete            => 1,
  :record_rename            => 1,
  :record_create            => 1,
  :api_import               => 1,
  :api_export               => 1,
  :data_quality_execute     => 1,
  :data_quality_design      => 1,
  :file_repository          => 1,
  :data_logging             => 1,
  :data_comparison_tool     => 1,
  :data_import_tool         => 1,
  :calendar                 => 1,
  :graphical                => 1,
  :reports                  => 1,
  :user_rights              => 1,
  :design                   => 1,
}

data = [record].to_json

fields = {
  :token   => Settings::API_TOKEN,
  :content => 'user',
  :format  => 'json',
  :data    => data,
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
