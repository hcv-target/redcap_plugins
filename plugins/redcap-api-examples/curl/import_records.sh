#!/bin/sh

. ./config

RECORD_ID=`date | openssl sha1 -hmac | tail -c 16`

DATA="token=$API_TOKEN&content=record&format=json&type=flat&data=[{\"record_id\":\"$RECORD_ID\",\"first_name\":\"First\",\"last_name\":\"Last\",\"address\":\"123%20Cherry%20Lane\nNashville,%20TN%2037015\",\"telephone\":\"(615)%20255-4000\",\"email\":\"first.last@gmail.com\",\"dob\":\"1972-08-10\",\"age\":\"43\",\"ethnicity\":\"1\",\"race\":\"4\",\"sex\":\"1\",\"height\":\"180\",\"weight\":\"105\",\"bmi\":\"32.4\",\"comments\":\"comments%20go%20here\",\"redcap_event_name\":\"event_1_arm_1\",\"basic_demography_form_complete\":\"2\"}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
