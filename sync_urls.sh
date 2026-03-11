#!/bin/bash
# sync_urls.sh — Reads events from Firebase Firestore and adds new sheet
# URLs to urls.conf so the cron cache picks them up automatically.
# Run every 5 minutes via cron.

FIRESTORE_URL="https://firestore.googleapis.com/v1/projects/powerlift-live/databases/(default)/documents/events"
URLS_CONF="/etc/pllapp/urls.conf"
LOG_PREFIX="[sync_urls]"

# Fetch events from Firestore REST API
response=$(curl -s --max-time 10 "$FIRESTORE_URL")

if [[ -z "$response" ]]; then
    echo "$LOG_PREFIX ERROR: Empty response from Firestore"
    exit 1
fi

# Extract sheetUrl values using Python
urls=$(echo "$response" | python3 -c "
import json, sys
data = json.load(sys.stdin)
for doc in data.get('documents', []):
    url = doc.get('fields', {}).get('sheetUrl', {}).get('stringValue', '')
    if url:
        print(url)
")

if [[ -z "$urls" ]]; then
    echo "$LOG_PREFIX No events found in Firestore"
    exit 0
fi

# Add any new URLs to urls.conf
new_count=0
while IFS= read -r url; do
    [[ -z "$url" ]] && continue
    if ! grep -qF "$url" "$URLS_CONF" 2>/dev/null; then
        echo "$url" >> "$URLS_CONF"
        new_count=$((new_count + 1))
        echo "$LOG_PREFIX Added new URL: $url"
    fi
done <<< "$urls"

if [[ $new_count -gt 0 ]]; then
    echo "$LOG_PREFIX $new_count new URL(s) added — triggering meta fetch"
    bash /opt/pllapp/fetch_meta.sh
else
    echo "$LOG_PREFIX All URLs already in urls.conf"
fi
