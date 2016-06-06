#!/usr/bin/env ruby

require 'digest/sha1'
require 'net/http'
require 'uri'
require './settings.rb'
include Settings

file = '/tmp/test_file.txt'
BOUNDARY = Digest::SHA1.hexdigest(Time.now.usec.to_s)

fields = {
  :token => Settings::API_TOKEN,
  :content => 'file',
  :action => 'import',
  :record => 'f21a3ffd37fc0b3c',
  :field => 'file_upload',
  :event => 'event_1_arm_1',
}

fields['returnFormat'] = 'json';

body = <<-EOF
--#{BOUNDARY}
Content-Disposition: form-data; name="file"; filename="#{File.basename(file)}"
Content-Type: application/octet-stream

#{File.read(file)}
--#{BOUNDARY}
#{fields.collect{|k,v|"Content-Disposition: form-data; name=\"#{k.to_s}\"\n\n#{v}\n--#{BOUNDARY}\n"}.join}

EOF

uri = URI.parse(Settings::API_URL)
http = Net::HTTP.new(uri.host, uri.port)
req = Net::HTTP::Post.new(uri.request_uri)
req.body = body
req['Content-Type'] = "multipart/form-data, boundary=#{BOUNDARY}"
resp = http.request(req)

puts resp.code
