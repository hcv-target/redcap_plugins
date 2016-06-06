#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';

my $fields = {
    token   => $config{api_token},
    content => 'pdf',
    format  => 'json'
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

open(OUTFILE, '>', '/tmp/export.pdf');
print OUTFILE $content;
close(OUTFILE);
