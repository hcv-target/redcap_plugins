#!/usr/bin/env ruby

require 'digest/sha1'
require 'json'
require 'curl'
require './settings.rb'
include Settings

record = {
  :record_id => Digest::SHA1.hexdigest(Time.now.usec.to_s)[0..16],
  :first_name => 'First',
  :last_name => 'Last',
  :address => '123 Cherry Lane\nNashville, TN 37015',
  :telephone => '(615) 255-4000',
  :email => 'first.last@gmail.com',
  :dob => '1972-08-10',
  :age => 43,
  :ethnicity => 1,
  :race => 4,
  :sex => 1,
  :height => 180,
  :weight => 105,
  :bmi => 32.4,
  :comments => 'comments go here',
  :redcap_event_name => 'events_2_arm_1',
  :basic_demography_form_complete => '2',
}

data = [record].to_json

fields = {
  :token => Settings::API_TOKEN,
  :content => 'record',
  :format => 'json',
  :type => 'flat',
  :data => data,
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
