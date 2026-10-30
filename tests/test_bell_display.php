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
