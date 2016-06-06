#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=event&format=json&arms="

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
