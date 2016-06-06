#!/usr/bin/env Rscript

source('config.R')
library(RCurl)
library(jsonlite)

record <- c(
	event_name='Event 1',
	arm_num=1,
	day_offset=0,
	offset_min=0,
	offset_max=0,
	unique_event_name='event_1_arm_1'
)

data <- toJSON(list(as.list(record)), auto_unbox=TRUE)

result <- postForm(
    api_url,
    token=api_token,
    content='event',
	action='import',
    format='json',
	override=0,
    data=data
)
print(result)
