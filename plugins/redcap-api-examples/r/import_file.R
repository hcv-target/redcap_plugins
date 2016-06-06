#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

file = '/tmp/test_file.txt'

result <- postForm(
    api_url,
    token=api_token,
    content='file',
    action='import',
    record='f21a3ffd37fc0b3c',
    field='file_upload',
    event='event_1_arm_1',
    returnFormat='json',
    file=fileUpload(file)
)
print(result)
