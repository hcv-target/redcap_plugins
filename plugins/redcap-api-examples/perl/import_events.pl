#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;
use JSON;

my %config = do 'config.pl';

my $record = {
	event_name        => 'Event 1',
	arm_num           => 1,
	day_offset        => 0,
	offset_min        => 0,
	offset_max        => 0,
	unique_event_name => 'event_1_arm_1',
};

my $json = JSON->new->allow_nonref;
my $data = $json->encode([$record]);

my $fields = {
    token    => $config{api_token},
    content  => 'event',
	action   => 'import',
    format   => 'json',
	override => 0,
    data     => $data,
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
