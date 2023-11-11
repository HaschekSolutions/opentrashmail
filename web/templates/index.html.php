<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/pico.min.css">
  <link rel="stylesheet" href="/css/fontawesome.min.css">
  <link rel="stylesheet" href="/css/opentrashmail.css">
  <title>Open Trashmail</title>
</head>

<body>
  <div class="container-fluid">
    <nav>
      <ul>
        <li><a href="/"><img src="/imgs/logo_300_light.png" width="50px" /> Open Trashmail</a></li>
        <li><input id="email" hx-post="/api/address" hx-target="#main" name="email" type="email" hx-trigger="input changed delay:500ms" placeholder="email address" aria-label="email address"></li>
        <li><button hx-get="/api/random" hx-target="#main"><i class="fas fa-random"></i> Generate random</button></li>
        <?php if($settings['SHOW_ACCOUNT_LIST']): ?><li><button hx-get="/api/listaccounts" hx-target="#main" hx-push-url="/listaccounts"><i class="fas fa-list"></i> List accounts</button></li><?php endif; ?>
      </ul>
    </nav>
  </div>

  <button class="htmx-indicator" aria-busy="true">Loading…</button>

  <main id="main" class="container" hx-get="/api/<?= $url ?>" hx-trigger="load">

  </main>

  <script src="/js/htmx.min.js"></script>
  <script src="/js/moment-with-locales.min.js"></script>
</body>

</html>