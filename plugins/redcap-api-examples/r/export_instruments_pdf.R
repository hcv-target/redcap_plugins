#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='pdf',
    format='json',
    binary=TRUE
)

f <- file('/tmp/export.pdf', 'wb')
writeBin(as.vector(result), f)
close(f)
