#!/usr/bin/env Rscript

source('config.R')
library(RCurl)
library(digest)
library(jsonlite)

record_id = substr(digest(Sys.time(), algo='sha1'), 0, 16)

record <- c(
    record_id=record_id,
    first_name='First',
    last_name='Last',
    address='123 Cherry Lane\nNashville, TN 37015',
    telephone='(615) 255-4000',
    email='first.last@gmail.com',
    dob='1972-08-10',
    age=43,
    ethnicity=1,
    race=4,
    sex=1,
    height=180,
    weight=105,
    bmi=31.4,
    comments='comments go here',
    redcap_event_name='event_1_arm_1',
    basic_demography_form_complete='2'
)

data <- toJSON(list(as.list(record)), auto_unbox=TRUE)

result <- postForm(
    api_url,
    token=api_token,
    content='record',
    format='json',
    type='flat',
    data=data
)
print(result)
