#!/bin/sh

. ./config

DATA="token=$API_SUPER_TOKEN&content=project&format=json&data=[{\"project_title\":\"New%20Project%20via%20API\",\"purpose\":0,\"purpose_other\":\"\",\"project_note\":\"Some%20notes%20about%20the%20project\"}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
