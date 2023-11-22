<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/pico.min.css">
  <link rel="stylesheet" href="/css/fontawesome.min.css">
  <link rel="stylesheet" href="/css/prism.css">
  <link rel="stylesheet" href="/css/opentrashmail.css">
  <title>Open Trashmail</title>
</head>

<body>
  <div class="topnav" id="OTMTopnav">
    <a href="/"><img src="/imgs/logo_300_light.png" width="50px" /> Open Trashmail</a>
    <a><input id="email" hx-post="/api/address" hx-target="#main" name="email" type="email" style="margin-bottom:0px" hx-trigger="input changed delay:500ms" placeholder="email address" aria-label="email address"></a>
    <a href="/random" hx-get="/api/random" hx-target="#main"><i class="fas fa-random"></i> Generate random</a>
    <?php if($settings['SHOW_ACCOUNT_LIST']): ?><a href="/listaccounts" hx-get="/api/listaccounts" hx-target="#main" hx-push-url="/listaccounts"><i class="fas fa-list"></i> List accounts</a><?php endif; ?>
    <?php if($settings['SHOW_LOGS']==true): ?><a href="/logs" hx-get="/api/logs" hx-target="#main" hx-push-url="/logs"><i class="fas fa-list"></i> Show logs</a><?php endif; ?>
    <a href="javascript:void(0);" class="icon" onclick="navbarmanager()">
      <i class="fa fa-bars"></i>
    </a>
  </div>

  <button class="htmx-indicator" aria-busy="true">Loading…</button>

  <main id="main" class="container" hx-get="/api/<?= $url ?>" hx-trigger="load">

  </main>

  <script src="/js/opentrashmail.js"></script>
  <script src="/js/htmx.min.js"></script>
  <script src="/js/moment-with-locales.min.js"></script>
</body>

</html>