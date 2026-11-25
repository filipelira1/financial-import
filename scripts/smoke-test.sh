#!/usr/bin/env bash
# End-to-end check of a running stack: sign in, import a CSV through the API, wait for the
# queue to finish it and compare the dashboard with the expected totals.
#
#   scripts/smoke-test.sh [file.csv]        (BASE_URL defaults to http://localhost:8090)
#
# Needs curl and python3. Expected totals are those of the 15,000-row sample file.
set -euo pipefail

BASE="${BASE_URL:-http://localhost:8090}/api/v1"
CSV="${1:-backend/tests/fixtures/financial_transactions.csv}"
EXPECTED_ROWS=15000
EXPECTED_INCOME=3611960974
EXPECTED_EXPENSE=2670954574
EXPECTED_BALANCE=941006400

# field '<python expression on d>' < json
field() { python3 -c "import json,sys; d=json.load(sys.stdin); print($1)"; }

token=$(curl -fsS -X POST "$BASE/auth/login" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"password"}' | field 'd["data"]["token"]')
auth=(-H "Authorization: Bearer $token" -H 'Accept: application/json')

started=$(date +%s)
response=$(curl -sS -w '\n%{http_code}' -X POST "$BASE/imports" "${auth[@]}" -F "file=@$CSV")
status=$(tail -n1 <<<"$response")
body=$(sed '$d' <<<"$response")

case "$status" in
  202) id=$(field 'd["data"]["id"]' <<<"$body") ;;
  409) id=$(field 'd["import_id"]' <<<"$body"); echo "This file was imported before: checking import #$id." ;;
  *) echo "Upload failed: HTTP $status $body" >&2; exit 1 ;;
esac

for _ in $(seq 1 180); do
  import=$(curl -fsS "$BASE/imports/$id" "${auth[@]}")
  [ "$(field 'd["data"]["is_finished"]' <<<"$import")" = "True" ] && break
  sleep 1
done

summary=$(curl -fsS "$BASE/dashboard/summary" "${auth[@]}")
elapsed=$(( $(date +%s) - started ))

python3 - "$import" "$summary" "$elapsed" <<PY
import json, sys
imp = json.loads(sys.argv[1])["data"]
summary = json.loads(sys.argv[2])["data"]
print(f"Import #{imp['id']}: {imp['status']}, {imp['imported_rows']} imported, {imp['failed_rows']} rejected ({sys.argv[3]} s).")
print(f"Dashboard: income {summary['income_cents']}, expense {summary['expense_cents']}, "
      f"balance {summary['balance_cents']}, {summary['transaction_count']} transactions (cents).")

problems = []
if imp["status"] != "completed": problems.append(f"status is {imp['status']}, expected completed")
if imp["imported_rows"] != $EXPECTED_ROWS: problems.append(f"imported {imp['imported_rows']}, expected $EXPECTED_ROWS")
if summary["transaction_count"] == $EXPECTED_ROWS:
    for key, expected in (("income_cents", $EXPECTED_INCOME), ("expense_cents", $EXPECTED_EXPENSE), ("balance_cents", $EXPECTED_BALANCE)):
        if summary[key] != expected: problems.append(f"{key} is {summary[key]}, expected {expected}")
else:
    print("Note: the account has other transactions too, so the totals are not compared.")

if problems:
    print("FAILED: " + "; ".join(problems), file=sys.stderr)
    sys.exit(1)
print("OK")
PY
