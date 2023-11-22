
<!DOCTYPE html>
<html>
<head>
    <title>Password Form</title>
</head>
<body>
    <h1>Enter Password</h1>
    <form action="/" method="POST">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <br><br>
        <input type="submit" value="Submit">
    </form>

    <h2><?=$error?></h2>
</body>
</html>
