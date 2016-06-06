#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='event',
    action='delete',
    'events[]'=c('event_1_arm_1')
)
print(result)
