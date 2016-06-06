#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=report&format=json&report_id=1"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
