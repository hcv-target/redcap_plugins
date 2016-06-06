#!/usr/bin/env Rscript

source('config.R')
library(RCurl)
library(jsonlite)

record <- c(
	arm_num=1,
	name='Arm 1'
)

data <- toJSON(list(as.list(record)), auto_unbox=TRUE)

result <- postForm(
    api_url,
    token=api_token,
    content='arm',
	action='import',
    format='json',
	override=0,
    data=data
)
print(result)
