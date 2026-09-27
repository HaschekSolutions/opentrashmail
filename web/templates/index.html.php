<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="referrer" content="no-referrer">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/css/pico.min.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/css/fontawesome.min.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/css/prism.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/css/opentrashmail.css">
  <title>Open Trashmail</title>
</head>

<body>
  <?php $pickdomains = array_values(array_filter(array_map('trim', explode(',', $settings['DOMAINS'] ?? '')), fn($d) => $d !== '' && strpos($d, '*') === false)); ?>
  <div class="topnav" id="OTMTopnav">
    <a href="<?= BASE_PATH ?>/"><img src="<?= BASE_PATH ?>/imgs/logo-50.png" width="50px" /> Open Trashmail <small class="version"><?=getVersion()?></small></a>
    <a><input id="email" hx-post="<?= BASE_PATH ?>/api/address" hx-target="#main" hx-vals='js:{email: otmFullEmail()}' type="text" style="margin-bottom:0px" hx-trigger="input changed delay:500ms, domainchange" placeholder="email address" aria-label="email address" autocomplete="off"></a>
    <?php if(count($pickdomains) > 0): ?>
    <a><select id="emaildomain" style="margin-bottom:0px" aria-label="domain" onchange="htmx.trigger('#email', 'domainchange')">
      <?php foreach($pickdomains as $d): ?><option value="<?= escape($d) ?>">@<?= escape($d) ?></option><?php endforeach; ?>
    </select></a>
    <?php endif; ?>
    <a href="<?= BASE_PATH ?>/random" hx-get="<?= BASE_PATH ?>/api/random" hx-target="#main"><i class="fas fa-random"></i> Generate random</a>
    <?php if($settings['ADMIN_ENABLED']==true):?><a href="<?= BASE_PATH ?>/admin" hx-get="<?= BASE_PATH ?>/api/admin" hx-target="#main" hx-push-url="<?= BASE_PATH ?>/admin"><i class="fas fa-user-shield"></i> Admin</a><?php endif; ?>
    <a href="javascript:void(0);" class="icon" onclick="navbarmanager()">
      <i class="fa fa-bars"></i>
    </a>
  </div>

  <button class="htmx-indicator" aria-busy="true">Loading…</button>

  <?php if(!empty($settings['NOTICE'])): ?>
  <div class="container"><article class="notice"><?= nl2br(escape(str_replace('\n', "\n", $settings['NOTICE']))) ?></article></div>
  <?php endif; ?>

  <main id="main" class="container" hx-get="<?= BASE_PATH ?>/api/<?= escape($url) ?>" hx-trigger="load"></main>

  <script src="<?= BASE_PATH ?>/js/opentrashmail.js"></script>
  <script src="<?= BASE_PATH ?>/js/htmx.min.js"></script>
  <script src="<?= BASE_PATH ?>/js/moment-with-locales.min.js"></script>
</body>

</html>
