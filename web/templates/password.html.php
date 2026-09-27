
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Password Form</title>
</head>
<body>
    <h1>Enter Password</h1>
    <form action="<?= BASE_PATH ?>/" method="POST">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <br><br>
        <input type="submit" value="Submit">
    </form>

    <h2><?= isset($error) ? escape($error) : '' ?></h2>
</body>
</html>
