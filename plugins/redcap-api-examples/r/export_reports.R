#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='report',
    format='json',
    report_id='1'
)
print(result)
