<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
</head>
<body>

<form action="http://test:test@localhost:10080/user/file" method="post" enctype="multipart/form-data">

User: <input type="text" name="uid" size="6">

File-Type: <select name="upload_type">
<option value="image">Image (Me)</option>
<option value="logo">Company Logo</option>
</select>

File: <input type="file" name="upload" />

<input type="submit" value="submit" />
        
</form>

<pre>php -S localhost:10081 ./post_multipart_form_data.php</pre>

</body>
</html>
