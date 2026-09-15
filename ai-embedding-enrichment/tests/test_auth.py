"""Unit tests for API key auth (no ML model load)."""

from __future__ import annotations

import pytest
from fastapi import HTTPException

from auth import API_SECRET_ENV, expected_api_key, verify_api_key


def test_it_should_return_env_key_when_configured(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv(API_SECRET_ENV, "test-secret-key")
    assert expected_api_key() == "test-secret-key"


def test_it_should_reject_when_secret_not_configured(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.delenv(API_SECRET_ENV, raising=False)
    with pytest.raises(HTTPException) as exc:
        verify_api_key(x_api_key="anything")
    assert exc.value.status_code == 401


def test_it_should_reject_when_key_mismatches(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv(API_SECRET_ENV, "correct-key")
    with pytest.raises(HTTPException) as exc:
        verify_api_key(x_api_key="wrong-key")
    assert exc.value.status_code == 401


def test_it_should_accept_when_key_matches(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv(API_SECRET_ENV, "correct-key")
    assert verify_api_key(x_api_key="correct-key") == "correct-key"
