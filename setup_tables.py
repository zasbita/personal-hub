#!/usr/bin/env python3
"""
Supabase Table Setup & RLS Fix
Mengecek dan memperbaiki permission issues pada tabel Serene Darwin
"""

import os
import sys

def main():
    print("""
╔═══════════════════════════════════════════════════════════════════╗
║  Supabase Table Setup & Permission Fix                           ║
║  Serene Darwin Project                                           ║
╚═══════════════════════════════════════════════════════════════════╝

📋 CHECKLIST YANG HARUS DILAKUKAN:
""")
    
    steps = [
        ("1", "Buka Supabase Dashboard", "https://app.supabase.com"),
        ("2", "Pilih project: mqgagjstasqoxtqjjwpa", ""),
        ("3", "Jalankan migration_sql.sql di SQL Editor", "Copy → Paste → RUN"),
        ("4", "Jalankan DISABLE_RLS.sql di SQL Editor", "Copy → Paste → RUN"),
        ("5", "Verify: Check RLS status", "Query di bawah"),
        ("6", "Test di Telegram: /update_km 5000", "Lalu /check_service"),
    ]
    
    for num, title, detail in steps:
        print(f"""
┌─ STEP {num}: {title}
│  {detail}
└─""")
    
    print("""

📝 QUERY VERIFIKASI (Step 5):
═════════════════════════════════════════════════════════════════════
SELECT tablename, rowsecurity 
FROM pg_tables 
WHERE schemaname = 'public' 
AND tablename IN ('users', 'vehicle_service', 'expenses', 'user_preferences', 'match_schedule');
═════════════════════════════════════════════════════════════════════

EXPECTED RESULT: Semua rowsecurity = false

tablename          | rowsecurity
─────────────────────────────────
users              | false
vehicle_service    | false
expenses           | false
user_preferences   | false
match_schedule     | false


🚀 QUICK FIX INSTRUCTIONS:
═════════════════════════════════════════════════════════════════════

Jika tabel SUDAH ada tapi RLS enabled:
  1. Buka https://app.supabase.com
  2. SQL Editor → New Query
  3. Copy isi DISABLE_RLS.sql
  4. Klik RUN

Jika tabel BELUM ada:
  1. SQL Editor → New Query
  2. Copy isi migration_sql.sql
  3. Klik RUN
  4. Lalu jalankan DISABLE_RLS.sql juga

═════════════════════════════════════════════════════════════════════

File yang sudah disiapkan:
  ✓ migration_sql.sql       - Create tabel
  ✓ DISABLE_RLS.sql         - Disable RLS
  ✓ setup_tables.py         - Script ini

""")

if __name__ == "__main__":
    main()
