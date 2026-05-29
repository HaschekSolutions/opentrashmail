<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= $url ?>/css/pico.min.css">
  <link rel="stylesheet" href="<?= $url ?>/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= $url ?>/css/prism.css">
  <link rel="stylesheet" href="<?= $url ?>/css/opentrashmail.css">
  <title>Open Trashmail</title>
</head>

<body>
  <div class="topnav" id="OTMTopnav">
    <a href="<?= $url ?>/"><img src="<?= $url ?>/imgs/logo-50.png" width="50px" /> Open Trashmail <small class="version"><?=getVersion()?></small></a>
    <a><input id="email" hx-post="<?= $url ?>/api/address" hx-target="#main" name="email" type="email" style="margin-bottom:0px" hx-trigger="input changed delay:500ms" placeholder="email address" aria-label="email address"></a>
    <a href="<?= $url ?>/random" hx-get="<?= $url ?>/api/random" hx-target="#main"><i class="fas fa-random"></i> Generate random</a>
    <?php if($this->settings['ADMIN_ENABLED']==true):?><a href="<?= $url ?>/admin" hx-get="<?= $url ?>/api/admin" hx-target="#main" hx-push-url="<?= $url ?>/admin"><i class="fas fa-user-shield"></i> Admin</a><?php endif; ?>
    <a href="javascript:void(0);" class="icon" onclick="navbarmanager()">
      <i class="fa fa-bars"></i>
    </a>
  </div>

  <button class="htmx-indicator" aria-busy="true">Loading…</button>

  <main id="main" class="container" hx-get="<?= $url ?>/api/<?= $url ?>" hx-trigger="load"></main>

  <script src="<?= $url ?>/js/opentrashmail.js"></script>
  <script src="<?= $url ?>/js/htmx.min.js"></script>
  <script src="<?= $url ?>/js/moment-with-locales.min.js"></script>
</body>

</html>
