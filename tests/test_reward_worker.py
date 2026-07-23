import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import url2pub_reward_jobs as jobs


class RewardWorkerTest(unittest.TestCase):
    def setUp(self):
        self.tempdir = tempfile.TemporaryDirectory()
        self.db_patch = patch.object(jobs, "STATE_DB", Path(self.tempdir.name) / "state.sqlite3")
        self.db_patch.start()

    def tearDown(self):
        self.db_patch.stop()
        self.tempdir.cleanup()

    def test_extracts_nested_transaction_hash(self):
        tx_hash = "0x" + "a" * 64
        self.assertEqual(jobs._find_tx_hash({"result": {"transactionHash": tx_hash}}), tx_hash)

    def test_builds_current_bankr_transfer_payload(self):
        wallet = "0x" + "1" * 40

        self.assertEqual(
            jobs._bankr_transfer_payload(wallet, "10000"),
            {
                "tokenAddress": jobs.TOKEN_CONTRACT,
                "recipientAddress": wallet,
                "amount": "10000",
                "isNativeToken": False,
                "chain": "base",
            },
        )

    def test_sends_once_and_returns_local_result_on_retry(self):
        tx_hash = "0x" + "b" * 64
        claim = {
            "id": "claim-1",
            "username": "testuser",
            "wallet": "0x" + "1" * 40,
            "amount": "10000",
            "status": "queued",
            "attempts": 0,
        }

        def callback(payload):
            if payload["action"] == "status":
                return {"ok": True, "claim": claim}
            return {"ok": True, "claim": claim}

        with patch.object(jobs, "_callback", side_effect=callback), patch.object(
            jobs, "_bankr_transfer", return_value=(tx_hash, {"txHash": tx_hash})
        ) as transfer:
            result = jobs.send_urlai_reward("claim-1", "testuser", claim["wallet"], "history-1")
            retry = jobs.send_urlai_reward("claim-1", "testuser", claim["wallet"], "history-1")

        self.assertEqual(result["status"], "sent")
        self.assertEqual(retry["status"], "already_sent")
        transfer.assert_called_once()


if __name__ == "__main__":
    unittest.main()
