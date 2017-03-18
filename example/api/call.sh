#!/bin/bash
#
# uncomment "curl ... " api call
#

## test invalid route
# curl -G -H "Accept: application/json" "http://test:test@localhost:10080/some/action/?a=hello&x=12"

## valid GET route - data is appended to query (overwrite)
# curl -G --data "a=yuck&x=5" -H "Content-Type: application/x-www-form-urlencoded" -H "Accept: application/json" "http://test:test@localhost:10080/user/388?a=hello&x=12"

## valid POST route - post is prefered to get - output is xml 
# curl --data "a=hello&x=12" -H "Accept: application/xml" "http://test:test@localhost:10080/user/manager/en?x=3&z=5"

## change user 1 data
# curl -X PUT --data '{"firstname":"John","lastname":"Doe"}' -H "Content-type: application/json" "http://test:test@localhost:10080/user/1"

## get user without id
# curl "http://test:test@localhost:10080/user"

## get user 17
# curl "http://test:test@localhost:10080/user/17"

## delete user file 132
# curl -X DELETE "http://test:test@localhost:10080/user/file/132"

## post file
# curl --form "uid=15" --form "upload_type=logo" --form "upload=@call.sh" "http://test:test@localhost:10080/user/file"

## change user file
curl -X PUT --form "fileupload=@call.sh" "http://test:test@localhost:10080/user/file/4"
