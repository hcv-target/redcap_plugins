#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=pdf&format=json"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      -o /tmp/export.pdf \
      $API_URL
