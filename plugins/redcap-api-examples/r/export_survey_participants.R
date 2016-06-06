#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='participantList',
    instrument='test_instrument',
    event='event_1_arm_1',
    format='json'
)
print(result)
