#!/usr/bin/env python3
from flask import Flask, request, jsonify
import json, os, re, subprocess, datetime, uuid

app = Flask(__name__)

EVENTS_FILE = '/var/www/html/events.json'
URLS_CONF   = '/etc/pllapp/urls.conf'
PASSWORD    = 'PLLdirector2026'   # change this on the server

def load_events():
    if os.path.exists(EVENTS_FILE):
        with open(EVENTS_FILE) as f:
            return json.load(f)
    return {'events': []}

def save_events(data):
    with open(EVENTS_FILE, 'w') as f:
        json.dump(data, f, indent=2)
    os.chmod(EVENTS_FILE, 0o644)

@app.route('/api/events', methods=['GET'])
def get_events():
    resp = jsonify(load_events())
    resp.headers['Access-Control-Allow-Origin'] = '*'
    return resp

@app.route('/api/register', methods=['POST', 'OPTIONS'])
def register_event():
    if request.method == 'OPTIONS':
        resp = app.make_default_options_response()
        resp.headers['Access-Control-Allow-Origin'] = '*'
        resp.headers['Access-Control-Allow-Headers'] = 'Content-Type'
        return resp

    data = request.get_json()
    if not data or data.get('password') != PASSWORD:
        return jsonify({'error': 'Invalid password'}), 401

    sheet_url = data.get('sheetUrl', '')
    match = re.search(r'/d/([a-zA-Z0-9_-]+)', sheet_url)
    if not match:
        return jsonify({'error': 'Invalid Google Sheets URL'}), 400

    spreadsheet_id = match.group(1)
    event = {
        'id':            str(uuid.uuid4())[:8],
        'name':          data.get('name', '').strip(),
        'date':          data.get('date', ''),
        'time':          data.get('time', ''),
        'location':      data.get('location', '').strip(),
        'sheetUrl':      sheet_url,
        'spreadsheetId': spreadsheet_id,
        'createdAt':     datetime.datetime.utcnow().isoformat() + 'Z'
    }

    events = load_events()
    events['events'].append(event)
    save_events(events)

    # Add URL to urls.conf if not already present
    with open(URLS_CONF, 'r') as f:
        existing = f.read()
    if sheet_url not in existing:
        with open(URLS_CONF, 'a') as f:
            f.write(f'\n{sheet_url}\n')

    # Trigger immediate cache fetch in background
    subprocess.Popen(['bash', '/opt/pllapp/fetch_meta.sh'])

    resp = jsonify({'success': True, 'event': event})
    resp.headers['Access-Control-Allow-Origin'] = '*'
    return resp

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000)
