#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;
use Digest::SHA1;
use JSON;

my %config = do 'config.pl';

my $sha1 = Digest::SHA1->new;
$sha1->add(localtime(time));

my $record = {
    record_id => substr($sha1->hexdigest, 0, 16),
    first_name => 'First',
    last_name => 'Last',
    address => "123 Cherry Lane\nNashville, TN 37015",
    telephone => '(615) 255-4000',
    email => 'first.last@gmail.com',
    dob => '1972-08-10',
    age => 43,
    ethnicity => 1,
    race => 4,
    sex => 1,
    height => 180,
    weight => 105,
    bmi => 32.4,
    comments => 'comments go here',
    redcap_event_name => 'events_2_arm_1',
    basic_demography_form_complete => 2,
};

my $json = JSON->new->allow_nonref;
my $data = $json->encode([$record]);

my $fields = {
    token   => $config{api_token},
    content => 'record',
    format  => 'json',
    type    => 'flat',
    data    => $data,
};

my $ch = LWP::Curl->new();
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
