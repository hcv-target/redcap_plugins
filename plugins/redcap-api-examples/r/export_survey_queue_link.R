#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='surveyQueueLink',
    record='f21a3ffd37fc0b3c',
    instrument='demographics',
    event='event_1_arm_1',
    format='json'
)
print(result)
