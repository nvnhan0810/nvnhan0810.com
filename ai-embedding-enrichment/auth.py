"""API key verification for embed/enrich endpoints."""

from __future__ import annotations

import os

from fastapi import Header, HTTPException

API_SECRET_ENV = "API_SECRET_KEY"


def expected_api_key() -> str:
    return os.environ.get(API_SECRET_ENV, "").strip()


def verify_api_key(x_api_key: str = Header(...)) -> str:
    expected = expected_api_key()
    if not expected or x_api_key != expected:
        raise HTTPException(status_code=401, detail="Unauthorized")
    return x_api_key
