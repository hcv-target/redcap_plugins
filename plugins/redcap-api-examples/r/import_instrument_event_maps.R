#!/usr/bin/env Rscript

source('config.R')
library(RCurl)
library(jsonlite)

record <- list(
	arm=list(
		number=1,
		event=list(
			list(
				unique_event_name='event_1_arm_1',
				form=list('instr_1', 'instr_2')
			),
			list(
				unique_event_name='event_2_arm_1',
				form=list('instr_1')
			)
		)
	)
)

data <- toJSON(record, auto_unbox=TRUE)

result <- postForm(
    api_url,
    token=api_token,
    content='formEventMapping',
    format='json',
    data=data
)
print(result)
