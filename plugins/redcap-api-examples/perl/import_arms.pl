#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;
use JSON;

my %config = do 'config.pl';

my $record = {
	arm_num => 1,
	name    => 'Arm 1'
};

my $json = JSON->new->allow_nonref;
my $data = $json->encode([$record]);

my $fields = {
    token    => $config{api_token},
    content  => 'arm',
	action   => 'import',
    format   => 'json',
    type     => 'flat',
	override => 0,
    data     => $data,
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
