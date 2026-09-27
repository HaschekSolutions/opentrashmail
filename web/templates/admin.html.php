<h1>Admin</h1>

<?php
    if(!empty($_REQUEST['password']) && hash_equals((string)$settings['ADMIN_PASSWORD'], (string)$_REQUEST['password']))
        $_SESSION['admin'] = true;
    else if(!empty($_REQUEST['password']))
        echo '<div class="error">Wrong password</div>';
?>

<?php if($settings['ADMIN_PASSWORD'] != "" && !$_SESSION['admin']): ?>
    <form method="post" hx-post="<?= BASE_PATH ?>/api/admin" hx-target="#main">
        <input type="password" name="password" placeholder="password" />
        <input type="submit" value="Login" />
    </form>
<?php return; endif; ?>


<nav>
  <ul>
    <li><?php if($settings['SHOW_ACCOUNT_LIST']): ?><a href="<?= BASE_PATH ?>/listaccounts" hx-get="<?= BASE_PATH ?>/api/listaccounts" hx-target="#adminmain" hx-push-url="<?= BASE_PATH ?>/listaccounts"><i class="fas fa-list"></i> List accounts</a><?php endif; ?></li>
    <li><?php if($settings['SHOW_LOGS']==true): ?><a href="<?= BASE_PATH ?>/logs" hx-get="<?= BASE_PATH ?>/api/logs" hx-target="#adminmain" hx-push-url="<?= BASE_PATH ?>/logs"><i class="fas fa-list"></i> Show logs</a><?php endif; ?></li>
  </ul>
</nav>


<?php if(!$settings['SHOW_ACCOUNT_LIST'] && !$settings['SHOW_LOGS']): ?>
    <p>Nothing to show. Enable <code>SHOW_ACCOUNT_LIST</code> and/or <code>SHOW_LOGS</code> in the config.ini (or as Docker environment variables) to list all accounts or show the logs here.</p>
<?php endif; ?>

<div id="adminmain"></div>