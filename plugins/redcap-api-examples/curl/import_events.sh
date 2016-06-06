#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=event&action=import&override=0&format=json&data=[{\"event_name\":\"Event%201\",\"arm_num\":\"1\",\"day_offset\":\"0\",\"offset_min\":\"0\",\"offset_max\":\"0\",\"unique_event_name\":\"event_1_arm_1\"}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
