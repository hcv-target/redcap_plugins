#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=surveyLink&record=f21a3ffd37fc0b3c&instrument=test_instrument&event=event_1_arm_1&format=json"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
