#!/usr/bin/env python3
"""
Geocode agent addresses from xlsx and update DB.
1. Read xlsx agent list
2. Match with DB agents by name
3. Geocode addresses via Google Maps Geocoding API
4. Update agent table with lat/lng/address
"""

import openpyxl
import json
import urllib.request
import urllib.parse
import time
import mysql.connector

GOOGLE_API_KEY = "AIzaSyCn9NqedIRpJrhkLDNRRr8yIPFaUFl-FnU"
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "cloud1234",
    "database": "smarttravel"
}

def geocode(address):
    """Convert address to lat/lng using Google Geocoding API"""
    params = urllib.parse.urlencode({
        "address": address + ", Philippines",
        "key": GOOGLE_API_KEY
    })
    url = f"https://maps.googleapis.com/maps/api/geocode/json?{params}"

    try:
        req = urllib.request.Request(url)
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode())

        if data["status"] == "OK" and data["results"]:
            loc = data["results"][0]["geometry"]["location"]
            return loc["lat"], loc["lng"]
        else:
            print(f"  Geocode failed ({data['status']}): {address}")
            return None, None
    except Exception as e:
        print(f"  Geocode error: {e}")
        return None, None

def normalize(name):
    """Normalize agency name for matching"""
    if not name:
        return ""
    return name.strip().upper().replace("'", "'").replace("\u2019", "'").replace("  ", " ")

def main():
    # 1. Read xlsx
    wb = openpyxl.load_workbook("/var/www/html/SMT Escape App (with access).xlsx", data_only=True)
    ws = wb["Form Responses 1"]

    xlsx_agents = {}
    for i, row in enumerate(ws.iter_rows(values_only=True)):
        if i == 0:
            continue
        name = (row[0] or "").strip()
        address = (row[1] or "").strip()
        phone = str(row[2] or "").strip()
        if name and address:
            xlsx_agents[normalize(name)] = {
                "name": name,
                "address": address,
                "phone": phone
            }

    print(f"XLSX: {len(xlsx_agents)} agents with addresses")

    # 2. Read DB agents
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, agencyName, storeName, storeAddress, latitude, longitude FROM agent")
    db_agents = cursor.fetchall()
    print(f"DB: {len(db_agents)} agents total")

    # 3. Match and geocode
    matched = 0
    geocoded = 0
    failed = 0

    for db_agent in db_agents:
        db_name = normalize(db_agent["agencyName"])
        if not db_name:
            continue

        # Try exact match first
        xlsx_data = xlsx_agents.get(db_name)

        # Try fuzzy match if no exact match
        if not xlsx_data:
            for xlsx_name, data in xlsx_agents.items():
                if db_name in xlsx_name or xlsx_name in db_name:
                    xlsx_data = data
                    break

        if not xlsx_data:
            continue

        matched += 1
        address = xlsx_data["address"]

        # Skip if already has coordinates
        if db_agent["latitude"] and db_agent["longitude"]:
            print(f"  [{db_agent['id']}] Already has coords: {db_agent['agencyName']}")
            continue

        # Geocode
        lat, lng = geocode(address)

        if lat and lng:
            cursor.execute(
                "UPDATE agent SET latitude=%s, longitude=%s, storeAddress=%s, storeName=%s WHERE id=%s",
                (lat, lng, address, xlsx_data["name"], db_agent["id"])
            )
            conn.commit()
            geocoded += 1
            print(f"  [{db_agent['id']}] OK: {xlsx_data['name']} -> {lat}, {lng}")
        else:
            failed += 1

        # Rate limit: ~10 requests per second
        time.sleep(0.1)

    cursor.close()
    conn.close()

    print(f"\n=== Results ===")
    print(f"Matched: {matched}")
    print(f"Geocoded: {geocoded}")
    print(f"Failed: {failed}")
    print(f"Unmatched DB agents: {len(db_agents) - matched}")

if __name__ == "__main__":
    main()
