<?php

# click stats per message
require_once dirname(__FILE__) . '/accesscheck.php';

if (isset($_GET['id'])) {
    $id = sprintf('%d', $_GET['id']);
} else {
    $id = 0;
}
$start = 0;
$limit = ' limit 10';
if (isset($_GET['start'])) {
    $start = sprintf('%d', $_GET['start']);
    $limit = ' limit ' . $start . ', 10';
}

$addcomparison = 0;
$access = accessLevel('statsoverview');
$ownership = '';
$subselect = '';
$paging = '';

#print "Access Level: $access";
switch ($access) {
    case 'owner':
        $ownership = sprintf(' and owner = %d ', $_SESSION['logindetails']['id']);
        if ($id) {
            $allow = Sql_Fetch_Row_query(sprintf('select owner from %s where id = %d %s', $GLOBALS['tables']['message'],
                $id, $ownership));
            if ($allow[0] != $_SESSION['logindetails']['id']) {
                print $GLOBALS['I18N']->get('You do not have access to this page');

                return;
            }
        }
        $addcomparison = 1;
        break;
    case 'all':
        break;
    case 'none':
    default:
        $ownership = ' and msg.id = 0';
        print $GLOBALS['I18N']->get('You do not have access to this page');

        return;
        break;
}

$download = !empty($_GET['dl']);
if ($download) {
    ob_end_clean();
#  header("Content-type: text/plain");
    header('Content-type: text/csv');
    if (!$id) {
        header('Content-disposition:  attachment; filename="phpList Campaign statistics.csv"');
    }
    ob_start();
}

if (!$id) {

   # print '<iframe id="contentiframe" src="./?page=pageaction&action=statsoverview&ajaxed=true' . addCsrfGetToken() . '" scrolling="no" width="100%" height="500"></iframe>';

    ## for testing the loader allow a delay flag
    if (isset($_GET['delay'])) {
        $_SESSION['LoadDelay'] = sprintf('%d', $_GET['delay']);
    } else {
        unset($_SESSION['LoadDelay']);
    }

    print '<div id="contentdiv"></div>';
    print '<script type="text/javascript">

        var loadMessage = \''.sjs('Please wait, your request is being processed. Do not refresh this page.').'\';
        var loadMessages = new Array(); 
        loadMessages[5] = \''.sjs('Still loading the statistics').'\';
        loadMessages[30] = \''.sjs('It may seem to take a while, but there is a lot of data to crunch<br/>if you have a lot of subscribers and campaigns').'\';
        loadMessages[60] = \''.sjs('It should be soon now, your stats are almost there.').'\';
        loadMessages[90] = \''.sjs('This seems to take longer than expected, looks like there is a lot of data to work on.').'\';
        loadMessages[120] = \''.sjs('Still loading, please be patient, your statistics will show shortly.').'\';
        loadMessages[150] = \''.sjs('It will really be soon now until your statistics are here.').'\';
        loadMessages[180] = \''.sjs('Maybe get a coffee instead, otherwise it is like watching paint dry.').'\';
        loadMessages[210] = \''.sjs('Still not here, let\'s have another coffee then.').'\';
        loadMessages[240] = \''.sjs('Too much coffee, I\'m trembling.').'\';
        var contentdivcontent = "./?page=pageaction&action=statsoverview&ajaxed=true&id='.$id.'&start='.$start . addCsrfGetToken() . '";
     </script>';

    return;
}

#print '<h3>'.$GLOBALS['I18N']->get('Campaign statistics').'</h3>';
print PageLinkButton('statsoverview', s('View all campaigns'));

$messagedata = loadMessageData($id);
//var_dump($messagedata);

if (empty($messagedata['subject'])) {
    Error(s('Campaign not found'));

    return;
}

print '<h3>' . $messagedata['subject'] . '</h3>';

$ls = new WebblerListing('');

$element = ucfirst(s('Subject'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', shortenTextDisplay($messagedata['subject'], 30));

$element = ucfirst(s('Date entered'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $messagedata['entered']);

$element = ucfirst(s('Date sent'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $messagedata['sent']);

$element = ucfirst(s('Sent as HTML'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $messagedata['astextandhtml']);

$element = ucfirst(s('Sent as text'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $messagedata['astext']);

$totalSent = 0;
$sentQ = Sql_Query(sprintf('select status,count(userid) as num from %s where messageid = %d group by status',
    $tables['usermessage'], $id));
while ($row = Sql_Fetch_Assoc($sentQ)) {
    $element = ucfirst($row['status']);
    $ls->addElement($element);
    $ls->addColumn($element, '&nbsp;', $row['num']);
    if ($row['status'] == 'sent') {
        $totalSent = $row['num'];
    }
}
/*
$element = ucfirst(s('Bounced'));
$ls->addElement($element);
$ls->addColumn($element,'&nbsp;',$messagedata['bouncecount']);
*/

$bounced = Sql_Fetch_Row_Query(sprintf('select count(distinct user) from %s where message = %d',
    $tables['user_message_bounce'], $id));
$element = ucfirst(s('Bounced'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $bounced[0]);
$totalBounced = $bounced[0];

$viewed = Sql_Fetch_Row_Query(sprintf('select count(userid) from %s where messageid = %d and status = "sent" and viewed is not null',
    $tables['usermessage'], $id));
$element = ucfirst(s('Opened'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', !empty($viewed[0]) ? PageLink2('mviews&id=' . $id, $viewed[0]) : '0');

$perc = sprintf('%0.2f', $viewed[0] / ($totalSent - $totalBounced) * 100);
$element = ucfirst(s('% Opened'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $perc);

$clicked = Sql_Fetch_Row_Query(sprintf('select count(userid) from %s where messageid = %d',
    $tables['linktrack_uml_click'], $id));
$element = ucfirst(s('Clicked'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', !empty($clicked[0]) ? PageLink2('mclicks&id=' . $id, $clicked[0]) : '0');

$perc = sprintf('%0.2f', $clicked[0] / ($totalSent - $totalBounced) * 100);
$element = ucfirst(s('% Clicked'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $perc);

$fwded = Sql_Fetch_Row_Query(sprintf('select count(id) from %s where message = %d',
    $GLOBALS['tables']['user_message_forward'], $id));
$element = ucfirst(s('Forwarded'));
$ls->addElement($element);
$ls->addColumn($element, '&nbsp;', $fwded[0]);

print $ls->display();
