"""RQDB4AI job entrypoints for URL2Pub URLAI rewards."""

from __future__ import annotations

import json
import os
import re
import sqlite3
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parent
STATE_DB = Path(os.environ.get("URL2PUB_REWARD_STATE_DB", ROOT / "storage/reward_worker.sqlite3"))
BANKR_API_BASE = os.environ.get("BANKR_API_BASE", "https://api.bankr.bot").rstrip("/")
TOKEN_CONTRACT = os.environ.get("URLAI_TOKEN_CONTRACT", "0xdaecdda6ad112f0e1e4097fb735dd01d9c33cba3")
EXPECTED_AMOUNT = os.environ.get("URL2PUB_REWARD_AMOUNT", "10000")


def _db() -> sqlite3.Connection:
    STATE_DB.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(STATE_DB, timeout=30)
    conn.execute(
        """CREATE TABLE IF NOT EXISTS payouts (
            claim_id TEXT PRIMARY KEY,
            wallet TEXT NOT NULL,
            amount TEXT NOT NULL,
            status TEXT NOT NULL,
            tx_hash TEXT,
            detail TEXT,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"""
    )
    return conn


def _api_key() -> str:
    key = os.environ.get("BANKR_API_KEY", "").strip()
    if key:
        return key
    path = Path.home() / ".bankr/config.json"
    if path.exists():
        try:
            return str(json.loads(path.read_text(encoding="utf-8")).get("apiKey") or "").strip()
        except (OSError, ValueError):
            pass
    raise RuntimeError("BANKR_API_KEY is not configured")


def _callback(payload: dict[str, Any]) -> dict[str, Any]:
    url = os.environ.get("URL2PUB_REWARD_CALLBACK_URL", "").strip()
    secret = os.environ.get("URL2PUB_REWARD_CALLBACK_SECRET", "").strip()
    if not url or not secret:
        raise RuntimeError("URL2Pub reward callback is not configured")
    request = urllib.request.Request(
        url,
        data=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "X-URL2Pub-Reward-Secret": secret,
            "User-Agent": "url2pub-reward-worker/1.0",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            return json.load(response)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")[:500]
        raise RuntimeError(f"URL2Pub callback failed: HTTP {exc.code} {body}") from exc


def _find_tx_hash(value: Any) -> str:
    if isinstance(value, str) and re.fullmatch(r"0x[a-fA-F0-9]{64}", value):
        return value.lower()
    if isinstance(value, dict):
        preferred = ("txHash", "tx_hash", "transactionHash", "transaction_hash", "hash")
        for key in preferred:
            if key in value:
                found = _find_tx_hash(value[key])
                if found:
                    return found
        for nested in value.values():
            found = _find_tx_hash(nested)
            if found:
                return found
    if isinstance(value, list):
        for nested in value:
            found = _find_tx_hash(nested)
            if found:
                return found
    return ""


def _bankr_transfer(wallet: str, amount: str, claim_id: str) -> tuple[str, dict[str, Any]]:
    payload = {"to": wallet, "token": TOKEN_CONTRACT, "amount": amount, "chain": "base"}
    request = urllib.request.Request(
        f"{BANKR_API_BASE}/wallet/transfer",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "X-API-Key": _api_key(),
            "Idempotency-Key": claim_id,
            "User-Agent": "url2pub-reward-worker/1.0",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=90) as response:
            result = json.load(response)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")[:1000]
        raise RuntimeError(f"Bankr transfer failed: HTTP {exc.code} {body}") from exc
    tx_hash = _find_tx_hash(result)
    if not tx_hash:
        raise RuntimeError("Bankr transfer returned no transaction hash")
    return tx_hash, result


def send_urlai_reward(
    claim_id: str,
    username: str,
    wallet: str,
    history_id: str,
    amount: str = "10000",
    **_: Any,
) -> dict[str, Any]:
    """Send one idempotent URLAI reward after validating the web ledger claim."""
    del history_id
    wallet = wallet.strip().lower()
    if not re.fullmatch(r"0x[a-f0-9]{40}", wallet):
        raise ValueError("invalid EVM wallet")
    if str(amount) != EXPECTED_AMOUNT:
        raise ValueError(f"reward amount must be {EXPECTED_AMOUNT} URLAI")

    with _db() as conn:
        existing = conn.execute("SELECT status, tx_hash FROM payouts WHERE claim_id = ?", (claim_id,)).fetchone()
        if existing and existing[0] == "sent":
            # A prior transfer may have succeeded while the web callback was down.
            # Replaying the RQ job repairs the public ledger without sending again.
            _callback({"action": "sent", "claim_id": claim_id, "tx_hash": existing[1]})
            return {"ok": True, "items": 1, "status": "already_sent", "tx_hash": existing[1]}
        if existing and existing[0] == "sending":
            raise RuntimeError("payout is in manual-review state after an interrupted transfer")

    remote = _callback({"action": "status", "claim_id": claim_id})
    claim = remote.get("claim") if remote.get("ok") else None
    if not isinstance(claim, dict):
        raise RuntimeError("reward claim is missing from URL2Pub ledger")
    if (
        str(claim.get("wallet", "")).lower() != wallet
        or str(claim.get("amount", "")) != str(amount)
        or str(claim.get("username", "")).lower() != str(username).lower()
    ):
        raise RuntimeError("reward job does not match URL2Pub ledger")
    if claim.get("status") == "sent" and claim.get("tx_hash"):
        with _db() as conn:
            conn.execute(
                "INSERT OR REPLACE INTO payouts(claim_id,wallet,amount,status,tx_hash,detail,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP)",
                (claim_id, wallet, amount, "sent", claim["tx_hash"], "synced from web ledger"),
            )
        return {"ok": True, "items": 1, "status": "already_sent", "tx_hash": claim["tx_hash"]}

    attempts = int(claim.get("attempts") or 0) + 1
    started = _callback({"action": "start", "claim_id": claim_id, "attempts": attempts})
    if not started.get("ok"):
        raise RuntimeError("reward claim could not be acquired")

    with _db() as conn:
        conn.execute(
            "INSERT OR REPLACE INTO payouts(claim_id,wallet,amount,status,tx_hash,detail,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP)",
            (claim_id, wallet, amount, "sending", None, "Bankr request started"),
        )

    try:
        tx_hash, result = _bankr_transfer(wallet, amount, claim_id)
        with _db() as conn:
            conn.execute(
                "UPDATE payouts SET status='sent', tx_hash=?, detail=?, updated_at=CURRENT_TIMESTAMP WHERE claim_id=?",
                (tx_hash, json.dumps(result, ensure_ascii=False)[:4000], claim_id),
            )
        callback = _callback({"action": "sent", "claim_id": claim_id, "tx_hash": tx_hash})
        if not callback.get("ok"):
            raise RuntimeError("transfer succeeded but URL2Pub ledger callback failed")
        return {"ok": True, "items": 1, "status": "sent", "tx_hash": tx_hash, "wallet": wallet, "amount": amount}
    except Exception as exc:
        with _db() as conn:
            row = conn.execute("SELECT status FROM payouts WHERE claim_id=?", (claim_id,)).fetchone()
            if not row or row[0] != "sent":
                conn.execute(
                    "UPDATE payouts SET status='failed', detail=?, updated_at=CURRENT_TIMESTAMP WHERE claim_id=?",
                    (str(exc)[:1000], claim_id),
                )
                try:
                    _callback({"action": "failed", "claim_id": claim_id, "message": str(exc)[:500]})
                except Exception:
                    pass
        raise
