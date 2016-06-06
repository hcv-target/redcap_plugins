#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=arm&action=import&override=0&format=json&data=[{\"arm_num\":\"1\",\"name\":\"Arm%201\"}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
