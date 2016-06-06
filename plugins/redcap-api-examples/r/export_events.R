#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='event',
    format='json',
    returnFormat='json',
    arms=''
)
print(result)
