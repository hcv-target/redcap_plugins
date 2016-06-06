#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=exportFieldNames&format=json&field=first_name"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
