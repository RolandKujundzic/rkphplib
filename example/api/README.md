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

## Swagger documentation

Create swagger yaml API documentation from annotations in ApiExample.class.php.

* @api: first annotation, comma separated list (e.g. version 1.0, param $id[:header], no_ref NAME, no_security NAME, ...)
* @api_summary: Summary
* @api_desc: Description
* @api_consumes: comma separated list (application/x-www-form-url-encoded, multipart/form-data)
* @api_produces: comma separated list (application/json, application/xml)
* @api_param: comma separated list (name, body|formData|header|path|query, required, default, desc[, extra1, ... ])

Re-create swagger.yaml file.

```sh
php bin/api2doc example/api/api2doc.json
```

## Files

* api2doc.json: Configuration file for api2doc
* ApiExample.class.php: API Server
* call.php: php test
* call.sh: curl test
* .htaccess: catch all routing for apache2
* index.php: run API server
* post_multipart_form_data.php: html form test
* README.md: this file
* routing.php: catch all routing for buildin php server
* swagger.yaml: swagger yaml documentation
* swagger_minimal.yaml: swagger yaml documentation template

## API Calls

* POST:/user/file
* PUT:/user/file/{id}
* DELETE:/user/file/{id}
* POST:/user/{type}/{locale}
* PUT:/user/{id}
* GET:/user/{id}
 
