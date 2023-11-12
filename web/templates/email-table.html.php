<nav aria-label="breadcrumb">
  <ul>
    <li><?= escape($email) ?></li>
    <li></li>
    <li></li>
  </ul>
</nav>

<div>
  <a role="button" class="outline" href="#" id="copyemailbtn" onclick="copyEmailToClipboard();return false;"><i class="far fa-clipboard"></i> Copy address to clipboard</a>
  <a role="button" class="outline" href="/rss/<?= $email ?>" target="_blank"><i class="fas fa-rss"></i> RSS Feed</a>
  <a role="button" class="outline" href="/json/<?= $email ?>" target="_blank"><i class="fas fa-file-code"></i> JSON API</a>
</div>

<table role="grid">
  <thead>
    <tr>
      <th scope="col">#</th>
      <th scope="col">Date</th>
      <th scope="col">From</th>
      <?php if($isadmin==true): ?><th scope="col">To</th><?php endif; ?>
      <th scope="col">Subject</th>
      <th scope="col">Action</th>
    </tr>
  </thead>
  <tbody>

    <?php if(count($emails)==0): ?>
    <tr>
      <td colspan="5"><center>No emails received on this address (yet..)</center></td>
    </tr>
    <?php endif; ?>

    <?php foreach($emails as $unixtime => $ed): ?>
        <tr>
            <th scope="row"><?= ++$i; ?></th>
            <td id="date-td-<?= $i ?>"><script>document.getElementById('date-td-<?= $i ?>').innerHTML = moment.unix(parseInt(<?=$unixtime?>/1000)).format('<?= $dateformat; ?>');</script></td>
            <td><?= escape($ed['from']) ?></td>
            <?php if($isadmin==true): ?><td><?= $ed['email'] ?></td><?php endif; ?>
            <td><?= escape($ed['subject']) ?></td>
            <td>
              <?php if($isadmin==true): ?>
                  <a href="/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-get="/api/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-push-url="/read/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-target="#main" role="button">Open</a>
                  <a href="#" hx-get="/api/delete/<?= $ed['email'] ?>/<?= $ed['id'] ?>" hx-confirm="Are you sure?" hx-target="closest tr" hx-swap="outerHTML swap:1s" role="button">Delete</a>
              <?php else: ?>
                  <a href="/read/<?= $email ?>/<?= $ed['id'] ?>" hx-get="/api/read/<?= $email ?>/<?= $ed['id'] ?>" hx-push-url="/read/<?= $email ?>/<?= $ed['id'] ?>" hx-target="#main" role="button">Open</a>
                  <a href="#" hx-get="/api/delete/<?= $email ?>/<?= $ed['id'] ?>" hx-confirm="Are you sure?" hx-target="closest tr" hx-swap="outerHTML swap:1s" role="button">Delete</a>
              <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<script>history.pushState({urlpath:"/address/<?= $email ?>"}, "", "/address/<?= $email ?>");</script>
<script>
  function copyEmailToClipboard(){
    navigator.clipboard.writeText("<?= $email ?>");
    document.getElementById('copyemailbtn').innerHTML = '<i class="fas fa-check-circle" style="color: green;"></i> Copied!';
  }
</script>