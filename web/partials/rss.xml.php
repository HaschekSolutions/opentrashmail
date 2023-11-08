<?xml version="1.0" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <atom:link href="<?= $url ?>/rss/<?= $email ?>" rel="self" type="application/rss+xml" />
  <title>RSS for <?= $email ?></title>
  <link><?= $url ?>/eml/<?= $email ?></link>
  <description>RSS Feed for email address <?= $email ?></description>
  <lastBuildDate><?= date(DateTime::RFC2822, time()) ?></lastBuildDate>
  <image>
      <title>RSS for <?= $email ?></title>
      <url>https://raw.githubusercontent.com/HaschekSolutions/opentrashmail/master/web/imgs/logo_300.png</url>
      <link>https://github.com/HaschekSolutions/opentrashmail</link>
  </image>
  <?php foreach ($emaildata as $id => $d): 
    $data = getEmail($email, $id);
    $time = substr($id, 0, -3);
    if (is_array($data['parsed']['attachments']))
        foreach ($data['parsed']['attachments'] as $filename) {
            $filepath = ROOT . DS . '..' . DS . 'data' . DS . $email . DS . 'attachments' . DS . $filename;
            $parts = explode('-', $filename);
            $fid = $parts[0];
            $fn = $parts[1];
            $url = 'https://' . $url . '/api.php?a=attachment&email=' . $email . '&id=' . $fid . '&filename=' . $fn;
            //$encl[] = '<enclosure url="'.rawurlencode($url).'" length="'.filesize($filepath).'" type="'.mime_content_type($filepath).'" />';
            $att_text[] = "<a href='$url' target='_blank'>$fn</a>";
        }
  ?>
    <item>
        <title><![CDATA[<?= $data['parsed']['subject'] ?>]]></title>
        <pubDate><?= date(DateTime::RFC2822, $time) ?></pubDate>
        <link><?= $url ?>/eml/<?= $email ?>/<?= $id ?></link>
        <description>
            <![CDATA[
            Email from: <?= escape($d['from']) ?><br/>
            Email to: <?= escape(implode(';',$data['rcpts'])) ?><br/>
            <?= ((count($att_text) > 0) ? 'Attachments:<br/>' . array2ul($att_text) . '<br/>' : '') ?>
            <a href="<?= $url ?>api/eml/test@0xv.eu/1699459401553/raw">View raw email</a> <br/>
            <br/>---------<br/><br/>
            <?= ($data['parsed']['htmlbody'] ? $data['parsed']['htmlbody'] : nl2br(htmlentities($data['parsed']['body']))) ?>
            ]]>
        </description>
    </item>
    <?php endforeach; ?>
</channel>
</rss> 