#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='project_xml',
    returnMetadataOnly='false',
    exportSurveyFields='false',
    exportDataAccessGroups='false',
    returnFormat='json'
)
print(result)
