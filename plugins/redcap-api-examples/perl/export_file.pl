#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';

my $fields = {
    token   => $config{api_token},
    content => 'file',
    action  => 'export',
    record  => 'f21a3ffd37fc0b3c',
    field   => 'file_upload',
    event   => 'event_1_arm_1'
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

open(OUTFILE, '>', '/tmp/file.raw');
print OUTFILE $content;
close(OUTFILE);
