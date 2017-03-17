<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
</head>
<body>


<form action="http://test:test@localhost:10080/file/upload" method="post" enctype="multipart/form-data">
<input type="hidden" name="x" value="10">
<input type="hidden" name="y" value="html form">

File: <input type="file" name="image" />

<input type="submit" value="submit" />
        
</form>

<pre>php -S localhost:10081 ./post_multipart_form_data.php</pre>

</body>
</html>
