#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=arm&action=delete&format=json&arms[0]=1"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
