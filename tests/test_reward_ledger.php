<?php
define('URL2PUB_REWARD_ENABLED', true);
define('URL2PUB_REWARD_AMOUNT', '10000');
define('URL2PUB_REWARD_LIMIT', 2);
require_once __DIR__ . '/../public/lib.php';

$backup = file_exists(U2P_REWARD_LEDGER) ? file_get_contents(U2P_REWARD_LEDGER) : null;
@unlink(U2P_REWARD_LEDGER);

function expect_true($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

try {
    $one = u2p_reward_reserve('UserOne', '0x' . str_repeat('1', 40), 'history-1');
    expect_true(!empty($one['eligible']), 'first user should be eligible');
    expect_true($one['claim']['amount'] === '10000', 'amount should be 10000 URLAI');

    $duplicate_user = u2p_reward_reserve('userone', '0x' . str_repeat('2', 40), 'history-2');
    expect_true(empty($duplicate_user['eligible']) && $duplicate_user['status'] === 'already_claimed', 'X user must be unique');

    $duplicate_wallet = u2p_reward_reserve('UserTwo', '0x' . str_repeat('1', 40), 'history-3');
    expect_true(empty($duplicate_wallet['eligible']) && $duplicate_wallet['status'] === 'already_claimed', 'wallet must be unique');

    $two = u2p_reward_reserve('UserTwo', '0x' . str_repeat('2', 40), 'history-4');
    expect_true(!empty($two['eligible']), 'second unique user should be eligible');

    $closed = u2p_reward_reserve('UserThree', '0x' . str_repeat('3', 40), 'history-5');
    expect_true(empty($closed['eligible']) && $closed['status'] === 'closed', 'campaign must stop at the cap');

    $updated = u2p_reward_update($one['claim']['id'], array('status' => 'sent', 'tx_hash' => '0x' . str_repeat('a', 64)), array('pending'));
    expect_true(!empty($updated['ok']) && $updated['claim']['status'] === 'sent', 'claim state should update atomically');
    echo "reward ledger tests: OK\n";
} finally {
    if ($backup === null) { @unlink(U2P_REWARD_LEDGER); }
    else { file_put_contents(U2P_REWARD_LEDGER, $backup); }
}
