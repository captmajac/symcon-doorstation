<?php

// Bell module: tile

$tests['Bell display: tile with initial payload and both actions'] = function () {
    $m = makeBell(['MailFlapVariable' => 404, 'MailDoorVariable' => 405]);
    $tile = $m->GetVisualizationTile();
    check(str_contains($tile, 'handleMessage(') && str_contains($tile, '\"mailbox\":0'), 'initial payload');
    foreach (["requestAction('SetMuted'", "requestAction('ResetMailbox'", 'textContent'] as $part) {
        check(str_contains($tile, $part), $part);
    }
    check(!str_contains($tile, 'innerHTML = state') && !preg_match('/innerHTML\s*=\s*[^\'"]/', $tile), 'values via innerHTML');
};

$tests['Bell display: mail and newspaper orange, no heading in the tile'] = function () {
    $m = makeBell(['MailFlapVariable' => 404, 'MailDoorVariable' => 405]);
    $colors = array_column(json_decode($m->presentations['Mailbox']['OPTIONS'], true), 'Color', 'Value');
    check($colors === [0 => -1, 1 => 0xFB8C00, 2 => 0xFB8C00], json_encode($colors));
    $tile = $m->GetVisualizationTile();
    check(str_contains($tile, '#FB8C00'), 'tile not orange');
    check(!str_contains($tile, 'class="head"') && !str_contains($tile, "translate('Bell')"), 'heading still there');
};

$tests['Bell display: motion as red badge, otherwise the last motion time'] = function () {
    $tile = makeBell(['MotionVariable' => 403])->GetVisualizationTile();
    check(str_contains($tile, '#E53935') && str_contains($tile, "'badge'"), 'no red badge');
    check(!str_contains($tile, "translate('No motion')"), 'still writes "No motion"');
};
