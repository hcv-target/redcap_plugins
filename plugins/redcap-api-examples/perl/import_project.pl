#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;
use JSON;

my %config = do 'config.pl';

my $record = {
	project_title => 'Project ABC',
	purpose       => 0,
	purpose_other => '',
	project_notes => 'Some notes about the project'
};

my $json = JSON->new->allow_nonref;
my $data = $json->encode($record);

my $fields = {
    token   => $config{api_super_token},
    content => 'project',
    format  => 'json',
    data    => $data,
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
