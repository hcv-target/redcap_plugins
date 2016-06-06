#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';

my $fields = {
    token      => $config{api_token},
    content    => 'surveyReturnCode',
    record     => 'f21a3ffd37fc0b3c',
    instrument => 'test_instrument',
    event      => 'event_1_arm_1',
    format     => 'json'
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
