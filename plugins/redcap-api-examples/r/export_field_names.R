#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='exportFieldNames',
    format='json',
    field='first_name'
)
print(result)
