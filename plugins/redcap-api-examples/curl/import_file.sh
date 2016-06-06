#!/bin/sh

. ./config

$CURL -H "Accept: application/json" \
      -F "token=$API_TOKEN" \
      -F "content=file" \
      -F "action=import" \
      -F "record=f21a3ffd37fc0b3c" \
      -F "field=file_upload" \
      -F "event=event_1_arm_1" \
      -F "filename=export.pdf" \
      -F "file=@/tmp/test_file.txt" \
      $API_URL
