<nav aria-label="breadcrumb">
  <ul>
    <li><a href="<?= BASE_PATH ?>/address/<?= escape($email) ?>" hx-get="<?= BASE_PATH ?>/api/address/<?= escape($email) ?>" hx-target="#main"><?= escape($email) ?></a></li>
    <li><?= escape($emaildata['parsed']['subject']) ?></li>
  </ul>
</nav>

<article>
    <header>
        <p>Subject: <?= escape($emaildata['parsed']['subject']) ?></p>
        <p>Received: <span id="date2-<?= $mailid ?>"><script>document.getElementById('date2-<?= $mailid ?>').innerHTML = moment.unix(parseInt(<?=$mailid?>/1000)).format('<?= $dateformat; ?>');</script></span></p>

        <p>
            Recipients:
            <?php foreach ($emaildata['rcpts'] as $to) : ?>
                <small class="badge"><?= escape($to) ?></small>
            <?php endforeach; ?>
        </p>
    </header>
    
    <div id="emailbody">
        <?php if($emaildata['parsed']['htmlbody']): ?>
            <a href="#" hx-confirm="Warning: HTML may contain tracking functionality or scripts. Do you want to proceed?" hx-get="<?= BASE_PATH ?>/api/raw-html/<?= escape($email) ?>/<?= $mailid ?>" hx-target="#emailbody" role="button" class="secondary outline">Render email in HTML</a>
        <?php endif; ?>
        <hr>
        <pre><?= nl2br(escape($emaildata['parsed']['body'])) ?></pre>
    </div>
    <footer>
        Attachments
        <div>
            <?php if (count($emaildata['parsed']['attachments']) == 0) : ?>
                <small class="secondary">No attachments</small>
            <?php endif; ?>
            <ul>
                <?php foreach ($emaildata['parsed']['attachments'] as $i => $attachment) : ?>
                    <li>
                        <a target="_blank" href="<?= BASE_PATH ?>/api/attachment/<?= escape(rawurlencode($email)) ?>/<?= escape(rawurlencode($attachment)) ?>"><?= escape($emaildata['parsed']['attachments_details'][$i]['filename'] ?? $attachment) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </footer>
</article>

<article>
    <header>Raw email</header>
    <a href="<?= BASE_PATH ?>/api/raw/<?= escape($email) ?>/<?= $mailid ?>" target="_blank">Open in new Window</a> |
    <a href="<?= BASE_PATH ?>/api/download/<?= escape($email) ?>/<?= $mailid ?>"><i class="fas fa-download"></i> Download .eml</a>
    <pre><button hx-get="<?= BASE_PATH ?>/api/raw/<?= escape($email) ?>/<?= $mailid ?>" hx-swap="outerHTML">Load Raw Email</button></pre>
</article>

<!-- 
<script>history.pushState({email:"<?= escape($email) ?>",id:"<?= $mailid ?>"}, "", "/read/<?= escape($email) ?>/<?= $mailid ?>");</script> -->
