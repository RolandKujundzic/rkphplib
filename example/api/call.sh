#!/bin/bash

# curl -G -H "Accept: application/json" "http://test:test@localhost:10080/some/action/?a=hallo&x=12"
# curl -G --data "a=yuck&x=5" -H "Content-Type: application/x-www-form-urlencoded" -H "Accept: application/json" "http://test:test@localhost:10080/some/action/?a=hallo&x=12"
# curl --data "a=hallo&x=12" -H "Accept: application/xml" "http://test:test@localhost:10080/signup/user/en_EN?x=3&z=5"
curl --form "a=15" --form "b=just testing" --form "fileupload=@call.sh" "http://test:test@localhost:10080/file/upload"
# curl -X PUT --form "fileupload=@call.sh" "http://test:test@localhost:10080/file/upload"
