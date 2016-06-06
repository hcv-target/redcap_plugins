#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';

my $fields = {
    token   => $config{api_token},
    content => 'file',
    action  => 'delete',
    record  => 'f21a3ffd37fc0b3c',
    field   => 'file_upload',
    event   => 'event_1_arm_1'
};

$fields->{returnFormat} = 'json';

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
