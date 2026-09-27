<div>
  <a role="button" class="outline" href="<?= BASE_PATH ?>/json/listaccounts" target="_blank"><i class="fas fa-file-code"></i> JSON API</a>
</div>

<table>
  <thead>
    <tr>
      <th scope="col">Email Addess</th>
      <th>Emails in Inbox</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($emails as $email): ?>
        <tr>
            <td>
                <a href="<?= BASE_PATH ?>/address/<?= escape($email) ?>" hx-get="<?= BASE_PATH ?>/api/address/<?= escape($email) ?>" hx-push-url="<?= BASE_PATH ?>/address/<?= escape($email) ?>" hx-target="#main">
                    <?= escape($email) ?>
                </a>
            </td>
            <td><?= countEmailsOfAddress($email); ?></td>
            <td>
            <a href="<?= BASE_PATH ?>/address/<?= escape($email) ?>" hx-get="<?= BASE_PATH ?>/api/address/<?= escape($email) ?>" hx-push-url="<?= BASE_PATH ?>/address/<?= escape($email) ?>" hx-target="#main" role="button" >Show</a>
            <a href="#" role="button" hx-get="<?= BASE_PATH ?>/api/deleteaccount/<?= escape($email) ?>" hx-confirm="Are you sure to delete this account and all its emails?" hx-target="closest tr" hx-swap="outerHTML swap:1s">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
  </tbody>
</table>