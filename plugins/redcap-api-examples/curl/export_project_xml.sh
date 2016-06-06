#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=project_xml&returnMetadataOnly=false&exportSurveyFields=false&exportDataAccessGroups=false&returnFormat=json"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
