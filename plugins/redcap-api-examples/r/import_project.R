#!/usr/bin/env Rscript

source('config.R')
library(RCurl)
library(jsonlite)

record <- c(
	project_title='Project ABC',
	purpose=0,
	purpose_other='',
	project_notes='Some notes about the project'
)

#data <- toJSON(list(as.list(record)), auto_unbox=TRUE)
data <- toJSON(as.list(record), auto_unbox=TRUE)

result <- postForm(
    api_url,
    token=api_super_token,
    content='project',
    format='json',
    data=data
)
print(result)
