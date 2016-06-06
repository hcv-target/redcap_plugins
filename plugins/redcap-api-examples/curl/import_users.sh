#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=user&format=json&data=[{\"username\":\"test_user_47\",\"expiration\":\"\",\"data_access_group\":\"1\",\"data_export\":\"0\",\"mobile_app\":\"0\",\"mobile_app_download_data\":\"0\",\"lock_record_multiform\":\"0\",\"lock_record\":\"0\",\"lock_record_customize\":\"0\",\"record_delete\":\"0\",\"record_rename\":\"0\",\"record_create\":\"1\",\"api_import\":\"1\",\"api_export\":\"1\",\"data_quality_execute\":\"1\",\"data_quality_design\":\"1\",\"file_repository\":\"1\",\"data_logging\":\"1\",\"data_comparison_tool\":\"1\",\"data_import_tool\":\"1\",\"calendar\":\"1\",\"graphical\":\"1\",\"reports\":\"1\",\"user_rights\":\"1\",\"design\":\"1\"}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
