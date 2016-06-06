#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=event&action=delete&format=json&events[0]=event_1_arm_1"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
