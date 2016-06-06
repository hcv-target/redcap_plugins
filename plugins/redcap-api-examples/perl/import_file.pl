#!/usr/bin/env perl

use strict;
use warnings;
use LWP::UserAgent;
use HTTP::Request::Common;

my %config = do 'config.pl';

my $file = '/tmp/test_file.txt';
my $ua = LWP::UserAgent->new;

my $req = $ua->request(
    POST $config{api_url},
    Content_Type => 'form-data',
    Content => [
	token   => $config{api_token},
	content => 'file',
	action  => 'import',
	record  => 'f21a3ffd37fc0b3c',
	field   => 'file_upload',
	event   => 'event_1_arm_1',
	file    => [$file]
    ]
);

print $req->is_success;
