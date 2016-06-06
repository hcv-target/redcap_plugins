#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';

my $fields = {
    token     => $config{api_token},
    content   => 'report',
    format    => 'json',
    report_id => 1
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
