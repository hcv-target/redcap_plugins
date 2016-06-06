#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=record&format=json&type=flat"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
