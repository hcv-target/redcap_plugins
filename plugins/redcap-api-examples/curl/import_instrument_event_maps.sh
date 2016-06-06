#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=formEventMapping&format=json&data=[{\"arm\":{\"number\":\"1\",\"event\":[{\"unique_event_name\":\"event_1_arm_1\",\"form\":[\"instr_1\",\"instr_2\"]}]}},{\"arm\":{\"number\":\"2\",\"event\":[{\"unique_event_name\":\"event_2_arm_1\",\"form\":[\"instr_1\"]}]}}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
