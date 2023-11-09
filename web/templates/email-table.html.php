<h3>Emails of <?= $email; ?></h3>

<table role="grid">
  <thead>
    <tr>
      <th scope="col">#</th>
      <th scope="col">Date</th>
      <th scope="col">From</th>
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
            <td><?= escape($ed['subject']) ?></td>
            <td>
                <form>
                    <input type="hidden" name="email" value="<?= $email ?>">
                    <input type="hidden" name="id" value="<?= $ed['id'] ?>">
                    <div class="grid">
                        <div><input type="submit" value="Read" hx-post="/api/read" hx-target="#main"></div>
                        <div><input type="submit" value="Delete" hx-post="/api/delete" hx-confirm="Are you sure?" hx-target="closest tr" hx-swap="outerHTML swap:1s"></div>
                    </div>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<script>history.pushState({email:"<?= $email ?>"}, "", "/eml/<?= $email ?>");</script>