# Simple Demo API

The buildin php webserver is used. If you are using apache2 copy .htaccess to
API server root directory.


## Start API Server

Use routing.php to dispatch all possible queries to index.php.

```sh
php -S localhost:10080 routing.php
```

## API Server Authentication

Basic authentication e.g. http://test:test@localhost:10080/user

## Start Multipart/Form-Upload Script

Upload a file to http://test:test@localhost:10080/user/file (POST). 

```sh
php -S localhost:10081 post_multipart_form_data.php
```

## Files

* ApiExample.class.php: API Server
* call.php: php test
* call.sh: curl test
* index.php: run API server
* post_multipart_form_data.php: html form test
* routing.php: catch all routing for buildin php server
* .htaccess: catch all routing for apache2

## API Calls

* POST:/user/file
* PUT:/user/file/{id}
* DELETE:/user/file/{id}
* POST:/user/{type}/{locale}
* PUT:/user/{id}
* GET:/user/{id}
 
