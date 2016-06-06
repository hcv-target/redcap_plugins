#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;
use JSON;

my %config = do 'config.pl';

my $record = {
	arm => {
		number => 1,
		event => [
			{
				unique_event_name => 'event_1_arm_1',
				form => [ 'instr_1', 'instr_2', ]
			},
			{
				unique_event_name => 'event_2_arm_1',
				form => [ 'instr_1', ]
			},
		]
	}
};

my $json = JSON->new->allow_nonref;
my $data = $json->encode([$record]);

my $fields = {
    token   => $config{api_token},
    content => 'formEventMapping',
    format  => 'json',
    data    => $data,
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
