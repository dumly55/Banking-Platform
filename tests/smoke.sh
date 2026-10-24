#!/usr/bin/env bash
# End-to-end smoke test. Drives the running app over HTTP with curl.
#
#   flask --app bank setup-db --fresh            # seed demo data (needs mail via log, i.e. SMTP_HOST empty)
#   flask --app bank run --port 8000 &
#   bash tests/smoke.sh
#
# Relies on the seeded demo users and on development mode showing the MFA code on the page.

set -u
BASE="${BASE_URL:-http://localhost:8000}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MAIL_LOG="$ROOT/storage/logs/mail.log"
WORK="$(mktemp -d)"
if command -v cygpath >/dev/null; then
    # Git Bash on Windows: hand the native curl Windows paths, and don't let the shell rewrite them
    export MSYS2_ARG_CONV_EXCL='*'
    WORK="$(cygpath -m "$WORK")"
fi
JAR="$WORK/cookies"
PASS=0
FAIL=0
trap 'rm -rf "$WORK"' EXIT

# 1x1 PNG used as a check image
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' | base64 -d > "$WORK/check.png"
echo 'not an image' > "$WORK/check.txt"

ok()   { PASS=$((PASS + 1)); printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad()  { FAIL=$((FAIL + 1)); printf '  \033[31mFAIL\033[0m %s\n' "$1"; }
expect()     { if grep -qF -- "$3" <<<"$2"; then ok "$1"; else bad "$1 (missing: $3)"; fi; }
expect_not() { if grep -qF -- "$3" <<<"$2"; then bad "$1 (unexpected: $3)"; else ok "$1"; fi; }
section() { printf '\n\033[1m%s\033[0m\n' "$1"; }

get()  { curl -s -b "$JAR" -c "$JAR" "$BASE/$1"; }
# POST and follow the redirect, returning the final page (so flash messages are visible)
post() { local path=$1; shift; curl -s -L -b "$JAR" -c "$JAR" "$BASE/$path" --data-urlencode "csrf=$(csrf)" "$@"; }
upload() { local path=$1; shift; curl -s -L -b "$JAR" -c "$JAR" "$BASE/$path" -F "csrf=$(csrf)" "$@"; }
csrf() { get contact | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//'; }
status() { curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/$1" "${@:2}"; }
location() { curl -s -D - -o /dev/null -b "$JAR" -c "$JAR" "$BASE/$1" | grep -i '^location:' | tr -d '\r'; }
calc() { awk "BEGIN { printf \"%.2f\", $1 }"; }
fresh_session() { rm -f "$JAR"; }
balance() { get "account?number=$1" | grep -o 'Balance: \$[0-9,.]*' | sed 's/[^0-9.]//g'; }
mfa_code() { get mfa | grep -o 'your code is <strong>[0-9]*' | grep -o '[0-9]*$'; }
verify_mfa() { post mfa --data-urlencode "code=$(mfa_code)"; }

login_user()     { fresh_session; post login -d "username=$1" -d "password=$2" >/dev/null; verify_mfa; }
login_employee() { fresh_session; post employee-login -d "employee_id=$1" -d "password=$2" >/dev/null; verify_mfa; }

DEMO_CHK=4100123456780001
DEMO_SAV=4200123456780002
JDOE_CHK=4100987654320003

section "Public pages"
for p in "" about services why-us contact login register employee-login forgot; do
    [ "$(status "$p")" = 200 ] && ok "/$p loads" || bad "/$p loads"
done
expect "protected page redirects to login" "$(fresh_session; location dashboard)" "login"

section "Customer login + MFA"
fresh_session
page=$(post login -d username=demo -d password=wrong)
expect "wrong password rejected" "$page" "Incorrect username or password."
page=$(post login -d username=demo -d password=Password123)
expect "correct password asks for MFA code" "$page" "Multi-Factor Authentication"
page=$(post mfa -d code=000000)
expect "wrong MFA code rejected" "$page" "The code you entered was invalid."
page=$(verify_mfa)
expect "correct MFA code logs in" "$page" "Welcome, Taylor!"
expect "dashboard lists accounts" "$page" "$DEMO_CHK"

section "CSRF"
[ "$(status open-account -d type=Checking)" = 419 ] && ok "POST without CSRF token is rejected" || bad "POST without CSRF token is rejected"

section "Transfers"
chk=$(balance $DEMO_CHK); sav=$(balance $DEMO_SAV)
page=$(post transfer -d from=$DEMO_SAV -d to=$DEMO_CHK -d amount=10.25)
expect "transfer between own accounts succeeds without MFA" "$page" 'Transferred $10.25'
[ "$(balance $DEMO_CHK)" = "$(calc "$chk + 10.25")" ] && ok "checking credited" || bad "checking credited"
[ "$(balance $DEMO_SAV)" = "$(calc "$sav - 10.25")" ] && ok "savings debited" || bad "savings debited"

page=$(post transfer -d from=$DEMO_CHK -d to=$JDOE_CHK -d amount=5)
expect "transfer to another customer requires MFA" "$page" "Multi-Factor Authentication"
chk=$(balance $DEMO_CHK)
page=$(verify_mfa)
expect "external transfer completes after MFA" "$page" "Transferred \$5.00 to account $JDOE_CHK"
[ "$(balance $DEMO_CHK)" = "$(calc "$chk - 5")" ] && ok "sender debited after MFA" || bad "sender debited after MFA"

expect "overdraft blocked" "$(post transfer -d from=$DEMO_CHK -d to=$DEMO_SAV -d amount=999999)" "Insufficient funds"
expect "negative amount blocked" "$(post transfer -d from=$DEMO_CHK -d to=$DEMO_SAV -d amount=-50)" "Enter a valid amount"
expect "transfer FROM someone else's account blocked" "$(post transfer -d from=$JDOE_CHK -d to=$DEMO_CHK -d amount=1)" "Choose one of your own accounts"
expect "unknown recipient blocked" "$(post transfer -d from=$DEMO_CHK -d to=4100000000000000 -d amount=1)" "Recipient account does not exist."
expect "viewing someone else's account blocked" "$(curl -s -L -b "$JAR" -c "$JAR" "$BASE/account?number=$JDOE_CHK")" "Account not found."

section "Accounts, ATM, deposits"
expect "open a savings account" "$(post open-account -d type=Savings)" "Savings account"
expect "ATM wrong PIN rejected" "$(post atm -d account=$DEMO_CHK -d pin=0000 -d amount=20)" "Incorrect PIN."
chk=$(balance $DEMO_CHK)
expect "ATM withdrawal" "$(post atm -d account=$DEMO_CHK -d pin=1234 -d amount=20)" 'Withdrawal of $20.00 successful.'
[ "$(balance $DEMO_CHK)" = "$(calc "$chk - 20")" ] && ok "withdrawal debited" || bad "withdrawal debited"
expect "ATM from someone else's account blocked" "$(post atm -d account=$JDOE_CHK -d pin=1234 -d amount=1)" "Account not found."
expect "deposit rejects non-images" \
    "$(upload deposit -F "account=$DEMO_CHK" -F amount=10 -F "frontCheck=@$WORK/check.txt" -F "backCheck=@$WORK/check.txt")" "must be JPG, PNG"
chk=$(balance $DEMO_CHK)
expect "check deposit submitted" \
    "$(upload deposit -F "account=$DEMO_CHK" -F amount=150.75 -F "frontCheck=@$WORK/check.png;type=image/png" -F "backCheck=@$WORK/check.png;type=image/png")" "is being processed"
[ "$(balance $DEMO_CHK)" = "$chk" ] && ok "pending deposit does not change balance yet" || bad "pending deposit does not change balance yet"
expect "pending deposit shows on account history" "$(get "account?number=$DEMO_CHK")" "Pending"
expect "customer cannot open employee pages" "$(location employee-deposits)" "dashboard"
DEPOSIT_TX=$(get "account?number=$DEMO_CHK" | grep -A3 '>Pending<' | grep -o 'Transaction #: [0-9]*' | head -1 | grep -o '[0-9]*$')
[ "$(status "check-image?id=$DEPOSIT_TX")" = 302 ] && ok "customer cannot fetch check images" || bad "customer cannot fetch check images"
[ "$(status logout)" = 302 ] && ok "logout" || bad "logout"
[ -n "$(location dashboard)" ] && ok "dashboard needs login after logout" || bad "dashboard needs login after logout"

section "Registration"
suffix=$RANDOM$RANDOM
reg=(-d first=Test -d last=User -d "username=user$suffix" -d password=Password123 -d cpassword=Password123
     -d pin=2468 -d cpin=2468 -d "email=user$suffix@example.com"
     -d "ssn=$(printf '7%08d' $((suffix % 100000000)))" -d "phone=$(printf '5%09d' $((suffix % 1000000000)))"
     -d lstate=CA -d "license=L$suffix" -d "address=1 Main St" -d city=Fresno -d state=CA -d zip=93701)
fresh_session
expect "new customer can register" "$(post register "${reg[@]}")" "Registration successful."
expect "duplicate username rejected" "$(post register "${reg[@]}")" "That username is already taken."
expect "weak password rejected" "$(post register -d password=short -d cpassword=short -d username=x)" "Password must be at least 8 characters"
expect "new customer can log in" "$(login_user "user$suffix" Password123)" "Welcome, Test!"

section "Employee: deposit review"
page=$(login_employee 100000003 Password123)
expect "teller logs in with MFA" "$page" "Welcome to work, Sam Rivera!"
expect_not "teller has no Hire button" "$page" "togglePopup('popup-hire')"
[ "$(status "check-image?id=$DEPOSIT_TX")" = 200 ] && ok "employee can view check image" || bad "employee can view check image"
expect "pending deposit listed" "$(get employee-deposits)" "\$150.75"
expect "teller approves deposit" "$(post employee-deposits -d "transaction=$DEPOSIT_TX" -d decision=approve)" "Deposit approved."
expect "deposit cannot be approved twice" "$(post employee-deposits -d "transaction=$DEPOSIT_TX" -d decision=approve)" "already reviewed"
expect "teller cannot hire (tier check is server-side)" "$(post employee -d action=hire -d email=x@example.com -d tier=1)" "You do not have permission"
expect "teller cannot terminate" "$(post employee -d action=terminate -d employee=100000001)" "You do not have permission"
fresh_session
login_user demo Password123 >/dev/null
[ "$(balance $DEMO_CHK)" = "$(calc "$chk + 150.75")" ] && ok "approved deposit credited to customer" || bad "approved deposit credited to customer"

section "Employee: hiring, registration, termination"
login_employee 100000002 Password123 >/dev/null
expect "manager cannot hire at own tier" "$(post employee -d action=hire -d email=x@example.com -d tier=2)" "tiers below your own"
hire_email="hire$suffix@example.com"
page=$(post employee -d action=hire -d "email=$hire_email" -d tier=1)
expect "manager sends offer" "$page" "Offer sent to $hire_email"
NEW_ID=$(grep -o 'Offer sent to [^ ]* (employee ID [0-9]*' <<<"$page" | grep -o '[0-9]*$')
grep -q "Your employee ID is $NEW_ID" "$MAIL_LOG" && ok "offer email written to mail log" || bad "offer email written to mail log"
expect "manager cannot terminate the admin" "$(post employee -d action=terminate -d employee=100000001)" "You cannot terminate that employee."
fresh_session
expect "wrong email for employee ID rejected" "$(post employee-register -d step=verify -d "employee_id=$NEW_ID" -d email=wrong@example.com)" "No pending offer"
page=$(post employee-register -d step=verify -d "employee_id=$NEW_ID" -d "email=$hire_email")
expect "new hire verified" "$page" "Employee Registration"
page=$(post employee-register -d first=Nia -d last=Hart -d password=Password123 -d cpassword=Password123 -d "phone=$(printf '6%09d' $((suffix % 1000000000)))" \
    -d "address=2 Main St" -d city=Fresno -d state=CA -d zip=93701 -d lstate=CA -d "license=E$suffix" -d "ssn=$(printf '6%08d' $((suffix % 100000000)))")
expect "new hire registers" "$page" "Registration successful."
expect "new hire can log in" "$(login_employee "$NEW_ID" Password123)" "Welcome to work, Nia Hart!"

login_employee 100000001 Password123 >/dev/null
expect "admin terminates new hire" "$(post employee -d action=terminate -d "employee=$NEW_ID")" "has been terminated"
fresh_session
expect "terminated employee cannot log in" "$(post employee-login -d "employee_id=$NEW_ID" -d password=Password123)" "no longer with the company"

section "Support tickets"
fresh_session
page=$(post contact -d name=Visitor -d email=visitor@example.com -d 'question=<script>alert(1)</script> Is this escaped?')
expect "contact form creates ticket" "$page" "submitted"
TICKET=$(grep -o 'Ticket #[0-9]*' <<<"$page" | head -1 | grep -o '[0-9]*')
login_employee 100000003 Password123 >/dev/null
page=$(get employee-inquiries)
expect "ticket listed for employees" "$page" "$TICKET"
expect "ticket text is HTML-escaped" "$page" "&lt;script&gt;alert(1)&lt;/script&gt;"
post employee-inquiries -d "ticket=$TICKET" -d action=resolve >/dev/null
expect "ticket resolved" "$(get employee-inquiries | tr -d '\n' | grep -o 'Resolved Inquiries.*')" "$TICKET"

section "Password reset"
fresh_session
page=$(post forgot -d email=jamie@bankofthefuture.test -d action=reset)
expect "reset asks for emailed code" "$page" "Multi-Factor Authentication"
page=$(verify_mfa)
expect "reset form shown after code" "$page" "Reset Password"
expect "password changed" "$(post reset-password -d password=NewPass456 -d cpassword=NewPass456)" "Password reset successful."
expect "old password no longer works" "$(fresh_session; post login -d username=jdoe -d password=Password123)" "Incorrect username or password."
expect "new password works" "$(login_user jdoe NewPass456)" "Welcome, Jamie!"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
