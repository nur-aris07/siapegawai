#!/usr/bin/env python3
"""Siapegawai REL-VERIFY-01D / A-25: isolated dummy-data MariaDB logical restore rehearsal.

Operator-only local QA helper, NOT a Laravel source patch or release sign-off.
Default is read-only preflight. --run creates exactly two absent disposable DBs,
runs Laravel migrations on the disposable source, inserts a synthetic fixture,
dumps/restores source, compares snapshots, and checks synthetic media parity.
No DROP DATABASE, no migration against the active application DB.
Compatible with Python >= 3.10; intended for Windows CMD/MariaDB Native 10.4.
"""
from __future__ import annotations

import argparse
import csv
import datetime as dt
import getpass
import hashlib
import io
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys
import tempfile
import time

EXPECTED_HEAD = "c304e1c948d01502ebf59a316fb90b88743fa6b2"
SOURCE_DB = "siapegawai_staging_source"
RESTORE_DB = "siapegawai_restore_verify_testing"
ACTIVE_DB = "siapegawai"
TEST_DB = "siapegawai_testing"
HOST = "127.0.0.1"
PORT = "3307"
MYSQL = Path(r"E:\Development\Database\MariaDB\10.4\bin\mysql.exe")
DUMP = Path(r"E:\Development\Database\MariaDB\10.4\bin\mysqldump.exe")
PHP = Path(r"E:\Development\Runtime\PHP\8.3\php.exe")
REPO_OUT = Path(".siapegawai-backup") / "rel-verify-01d" / "a25"


def stamp() -> str:
    return dt.datetime.now(dt.timezone(dt.timedelta(hours=7))).strftime("%Y%m%dT%H%M%S%z")


def sha_file(p: Path) -> str:
    h = hashlib.sha256()
    with p.open("rb") as f:
        while True:
            chunk = f.read(1024 * 1024)
            if not chunk:
                break
            h.update(chunk)
    return h.hexdigest()


def checked_name(name: str) -> str:
    if name not in (SOURCE_DB, RESTORE_DB):
        raise ValueError("Unexpected DB name; refused")
    return name


def sql_ident(name: str) -> str:
    checked_name(name)
    return "`" + name + "`"


def no_active_target(src: str, dst: str) -> None:
    if src != SOURCE_DB or dst != RESTORE_DB or src == dst or src in (ACTIVE_DB, TEST_DB) or dst in (ACTIVE_DB, TEST_DB):
        raise ValueError("Unsafe source/target DB; refused")


def run_cmd(args: list[str], *, title: str, input_file=None, output_file=None, capture: bool=False, env=None) -> str:
    kw = {"check": False, "env": env}
    if input_file is not None:
        kw["stdin"] = input_file
    if output_file is not None:
        kw["stdout"] = output_file
    if capture:
        kw["stdout"] = subprocess.PIPE
        kw["stderr"] = subprocess.PIPE
    else:
        kw["stderr"] = subprocess.PIPE
    proc = subprocess.run(args, **kw)
    if proc.returncode != 0:
        # Never print raw stderr: may contain a local path / account identifier.
        raise RuntimeError(f"{title}: exit {proc.returncode}; stderr suppressed")
    if capture:
        return (proc.stdout or b"").decode("utf-8", errors="replace").replace("\r\n", "\n").strip()
    return ""


def output_text(args: list[str], title: str) -> str:
    return run_cmd(args, title=title, capture=True)


def git_guard(project: Path) -> None:
    if not (project / "artisan").is_file() or not (project / "vendor" / "autoload.php").is_file():
        raise RuntimeError("Project root/vendor autoload not found")
    if not (project / ".git").exists():
        raise RuntimeError("Not a Git checkout")
    head = output_text(["git", "-C", str(project), "rev-parse", "HEAD"], "git HEAD")
    branch = output_text(["git", "-C", str(project), "branch", "--show-current"], "git branch")
    changed = output_text(["git", "-C", str(project), "status", "--porcelain", "--untracked-files=no"], "git tracked status")
    if head != EXPECTED_HEAD or branch != "main" or changed:
        raise RuntimeError("HEAD/branch/tracked changes differ from accepted v1.15 baseline; stop")
    if not (project / "tools" / "rel-verify" / "SGP-REL-VERIFY-01B-PRIVATE-BACKUP-CHECK.php").is_file():
        raise RuntimeError("01B parity checker missing")
    for p in [MYSQL, DUMP, PHP]:
        if not p.is_file():
            raise RuntimeError("Expected native binary not present (see paths in script)")
    if (project / "bootstrap" / "cache" / "config.php").exists():
        raise RuntimeError("Laravel config cache exists. Run php artisan optimize:clear in local mode and retry")
    env_file = project / ".env"
    if not env_file.is_file():
        raise RuntimeError(".env missing")
    env_vals = {}
    for line in env_file.read_text(encoding="utf-8-sig").splitlines():
        match = re.match(r"^\s*([A-Z_]+)=(.*)$", line)
        if match and match.group(1) in ("APP_ENV", "APP_DEBUG", "DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE"):
            env_vals[match.group(1)] = match.group(2).strip().strip('"\'')
    want = {
        "APP_ENV": "local", "APP_DEBUG": "true", "DB_CONNECTION": "mysql", "DB_HOST": HOST,
        "DB_PORT": PORT, "DB_DATABASE": ACTIVE_DB,
    }
    if any(env_vals.get(k, "").lower() != v for k, v in want.items()):
        raise RuntimeError("Unexpected .env mode/DB details; refusing to modify or switch active DB")
    print("[PASS] Git v1.15 clean (tracked), .env local, no config cache, native CLI found")


def whoami_sid() -> str:
    out = output_text(["whoami", "/user", "/fo", "csv", "/nh"], "Windows identity")
    for row in csv.reader(io.StringIO(out)):
        if len(row) >= 2 and re.fullmatch(r"S-1-5(?:-\d+)+", row[1].strip()):
            return row[1].strip()
    raise RuntimeError("Cannot resolve Windows SID for temporary credential ACL")


def private_options_dir() -> Path:
    base = Path(os.environ.get("LOCALAPPDATA") or "")
    if not base.is_dir():
        raise RuntimeError("LOCALAPPDATA missing; cannot safely stage temporary credential")
    folder = Path(tempfile.mkdtemp(prefix="sgp-rel01d-cred-", dir=str(base)))
    sid = whoami_sid()
    p = subprocess.run(["icacls", str(folder), "/inheritance:r", "/grant:r", f"*{sid}:(OI)(CI)F"],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    if p.returncode != 0:
        shutil.rmtree(folder, ignore_errors=True)
        raise RuntimeError("Unable to restrict temporary credential directory ACL; STOP")
    return folder


def option_file(folder: Path, password: str) -> Path:
    if not password or "\x00" in password or "\n" in password or "\r" in password:
        raise RuntimeError("Invalid/empty MariaDB password; stop")
    secret = password.replace("\\", "\\\\").replace('"', '\\"')
    conf = folder / "client.cnf"
    with conf.open("x", encoding="utf-8", newline="\n") as f:
        f.write('[client]\nuser=root\npassword="' + secret + '"\nhost=' + HOST + '\nport=' + PORT + '\nprotocol=tcp\n')
    return conf


def mysql_args(conf: Path, *extra: str) -> list[str]:
    return [str(MYSQL), "--defaults-extra-file=" + str(conf), "--batch", "--skip-column-names",
            "--raw", "--silent", "--default-character-set=utf8mb4", *extra]


def mysql_query(conf: Path, sql: str, title: str, database: str | None=None) -> list[str]:
    args = mysql_args(conf)
    if database:
        args.append("--database=" + checked_name(database))
    args.append("--execute=" + sql)
    text = output_text(args, title)
    return text.splitlines() if text else []


def db_preflight(conf: Path) -> None:
    row = mysql_query(conf, "SELECT VERSION(), @@port, DATABASE()", "native DB identity")
    if len(row) != 1:
        raise RuntimeError("Unexpected MariaDB identity response")
    cells = row[0].split("\t")
    if len(cells) != 3 or "MariaDB" not in cells[0] or cells[1] != PORT or cells[2] not in ("NULL", ""):
        raise RuntimeError("Not the expected MariaDB Native service on port 3307; stop")
    names = mysql_query(conf,
        "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN "
        "('siapegawai_staging_source','siapegawai_restore_verify_testing') ORDER BY SCHEMA_NAME",
        "existing DB names")
    if names:
        raise RuntimeError("Disposable source/target database ALREADY EXISTS; no overwrite or DROP permitted")
    print("[PASS] Connected MariaDB Native port 3307; both disposable DB names are absent")


def migration_guard_php() -> str:
    # PHP code below is run ONLY in a subprocess with DB_DATABASE redirected to the dummy SOURCE DB.
    return r'''<?php
declare(strict_types=1);
chdir($argv[1]);
$src = 'siapegawai_staging_source';
$secret = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
if (($secret['user'] ?? '') !== 'root' || !is_string($secret['password'] ?? null)) {
    fwrite(STDERR, "[STOP] Missing dedicated temporary migration credentials\n"); exit(2);
}
foreach (['DB_DATABASE'=>$src, 'DB_USERNAME'=>'root', 'DB_PASSWORD'=>$secret['password']] as $key=>$val) {
    putenv($key.'='.$val); $_ENV[$key]=$val; $_SERVER[$key]=$val;
}
unset($secret);
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$conn = config('database.default');
$details = config('database.connections.'.$conn);
if ($conn !== 'mysql' || ($details['database'] ?? '') !== $src ||
    (string)($details['port'] ?? '') !== '3307' || ($details['host'] ?? '') !== '127.0.0.1' ||
    ($details['username'] ?? '') !== 'root' || !app()->environment('local') || is_file('bootstrap/cache/config.php')) {
    fwrite(STDERR, "[STOP] Laravel migration guard mismatch\n"); exit(3);
}
$actual = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name, @@port AS port');
if (!$actual || $actual->name !== $src || (int)$actual->port !== 3307) {
    fwrite(STDERR, "[STOP] PDO connection identity mismatch\n"); exit(4);
}
$code = Illuminate\Support\Facades\Artisan::call('migrate', ['--database'=>'mysql','--force'=>true,'--no-interaction'=>true]);
if ($code !== 0) { fwrite(STDERR, "[STOP] Migrations on disposable source failed\n"); exit(5); }
echo "[PASS] Laravel migrations executed ONLY on disposable source DB\n";
'''


def fixture_init(conf: Path, marker: str) -> None:
    if not re.fullmatch(r"[a-f0-9]{64}", marker):
        raise RuntimeError("Synthetic marker rejected")
    sql = ("CREATE TABLE `sgp_rel_verify_probe` (id BIGINT UNSIGNED PRIMARY KEY, "
           "marker CHAR(64) NOT NULL, INDEX idx_marker (marker)) ENGINE=InnoDB; "
           "INSERT INTO `sgp_rel_verify_probe` (id, marker) VALUES (1, '" + marker + "');")
    mysql_query(conf, sql, "seed synthetic probe", SOURCE_DB)
    print("[PASS] Synthetic probe inserted in disposable source DB (no app user data)")


def dump_safe(path: Path) -> None:
    if path.stat().st_size < 1000:
        raise RuntimeError("Dump unexpectedly small; stop")
    with path.open("rt", encoding="utf-8", errors="replace") as f:
        for line in f:
            stripped = line.lstrip()
            if re.match(r"^(CREATE|DROP)\s+DATABASE\b|^USE\s+", stripped, re.I):
                raise RuntimeError("Dump includes database-selection/creation statement; refuse import")
    print("[PASS] Dump created without CREATE/DROP DATABASE or USE statements")


def snapshot(conf: Path, name: str) -> dict:
    name = checked_name(name)
    # Metadata only, no stored row values exported in evidence.
    qnames = ("START TRANSACTION READ ONLY; SELECT TABLE_NAME FROM information_schema.TABLES "
              f"WHERE TABLE_SCHEMA='{name}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME; COMMIT;")
    names = mysql_query(conf, qnames, "table list", name)
    if not names or len(names) > 200 or any(not re.fullmatch(r"[A-Za-z0-9_]{1,64}", s) for s in names):
        raise RuntimeError("Unexpected schema inventory")
    if "migrations" not in names or "sgp_rel_verify_probe" not in names:
        raise RuntimeError("Missing migration or synthetic probe table")
    queries = {
        "columns": "SELECT CONCAT_WS('|', TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'[SQL_NULL]'),EXTRA,COALESCE(COLLATION_NAME,'[SQL_NULL]')) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION",
        "indexes": "SELECT CONCAT_WS('|',TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,COALESCE(SUB_PART,'[SQL_NULL]')) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",
        "constraints": "SELECT CONCAT_WS('|',TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,COALESCE(REFERENCED_TABLE_NAME,'[SQL_NULL]'),COALESCE(REFERENCED_COLUMN_NAME,'[SQL_NULL]')) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION",
        "migrations": "SELECT CONCAT(migration,CHAR(9),batch) FROM `migrations` ORDER BY migration",
    }
    data = {}
    for label, sql in queries.items():
        lines = mysql_query(conf, "START TRANSACTION READ ONLY; " + sql + "; COMMIT;", label, name)
        data[label] = hashlib.sha256("\n".join(lines).encode("utf-8")).hexdigest()
        if label == "migrations":
            data["migration_count"] = len(lines)
    union = " UNION ALL ".join("SELECT '" + t + "', COUNT(*) FROM `" + t + "`" for t in names)
    counts_lines = mysql_query(conf, "START TRANSACTION READ ONLY; " + union + "; COMMIT;", "table counts", name)
    counts = {}
    for line in counts_lines:
        parts = line.split("\t")
        if len(parts) != 2 or parts[0] not in names or not parts[1].isdigit():
            raise RuntimeError("Row count response invalid")
        counts[parts[0]] = int(parts[1])
    if len(counts) != len(names):
        raise RuntimeError("Count inventory incomplete")
    marker_lines = mysql_query(conf, "START TRANSACTION READ ONLY; SELECT SHA2(marker,256) FROM `sgp_rel_verify_probe` WHERE id=1; COMMIT;", "probe content hash", name)
    if len(marker_lines) != 1:
        raise RuntimeError("Synthetic content missing")
    return {"names": names, "counts": counts, "digests": data, "probe_sha256": marker_lines[0]}


def compare(a: dict, b: dict, expected_marker: str) -> dict:
    checks = {
        "table_names_equal": a["names"] == b["names"],
        "columns_equal": a["digests"]["columns"] == b["digests"]["columns"],
        "indexes_equal": a["digests"]["indexes"] == b["digests"]["indexes"],
        "constraints_equal": a["digests"]["constraints"] == b["digests"]["constraints"],
        "migrations_equal": a["digests"]["migrations"] == b["digests"]["migrations"],
        "counts_equal": a["counts"] == b["counts"],
        "probe_content_equal": a["probe_sha256"] == b["probe_sha256"] == hashlib.sha256(expected_marker.encode()).hexdigest(),
        "real_application_tables_present": len(a["names"]) > 10,
        "laravel_migrations_present": a["digests"]["migration_count"] >= 15,
    }
    return {"checks": checks, "all_pass": all(checks.values()),
            "tables_source": len(a["names"]), "tables_restore": len(b["names"]),
            "migrations_source": a["digests"]["migration_count"],
            "migrations_restore": b["digests"]["migration_count"]}


def pdf_fixture() -> bytes:
    # Valid, minimal, synthetic PDF (no participant data). No PDF is copied from the live app.
    objects = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Contents 4 0 R >>",
        b"<< /Length 0 >>\nstream\n\nendstream",
    ]
    buf = bytearray(b"%PDF-1.4\n")
    offsets = [0]
    for i, body in enumerate(objects, 1):
        offsets.append(len(buf))
        buf.extend(f"{i} 0 obj\n".encode() + body + b"\nendobj\n")
    xref = len(buf)
    buf.extend(f"xref\n0 {len(offsets)}\n0000000000 65535 f \n".encode())
    for offset in offsets[1:]:
        buf.extend(f"{offset:010d} 00000 n \n".encode())
    buf.extend(f"trailer\n<< /Size {len(offsets)} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode())
    return bytes(buf)


def parity(project: Path, conf_dump: Path, work: Path) -> str:
    a, b = work / "media-source-synthetic", work / "media-restore-synthetic"
    a.mkdir(parents=True, exist_ok=False)
    b.mkdir(parents=True, exist_ok=False)
    content = pdf_fixture()
    (a / "dummy-a25.pdf").write_bytes(content)
    (b / "dummy-a25.pdf").write_bytes(content)
    check = project / "tools" / "rel-verify" / "SGP-REL-VERIFY-01B-PRIVATE-BACKUP-CHECK.php"
    cmd = [str(PHP), str(check), "--run", "--dump=" + str(conf_dump),
           "--source-private=" + str(a), "--restored-private=" + str(b),
           "--expected-dump-sha256=" + sha_file(conf_dump),
           "--out-dir=" + str(project / ".siapegawai-backup" / "rel-verify-01d" / "a25" / "file-parity")]
    result = output_text(cmd, "01B synthetic parity")
    if "PASS_FILE_PARITY_ONLY" not in result:
        raise RuntimeError("01B synthetic media parity did not PASS; inspect ignored local evidence")
    print("[PASS] 01B synthetic private-PDF copy parity + dump SHA (not real user-media UAT)")
    return "PASS_FILE_PARITY_ONLY"


def self_test() -> None:
    for bad in (ACTIVE_DB, TEST_DB, RESTORE_DB + "_oops", "foo", "siapegawai"):
        try:
            no_active_target(bad, RESTORE_DB)
        except ValueError:
            pass
        else:
            raise AssertionError("Guard accepted unsafe source")
    no_active_target(SOURCE_DB, RESTORE_DB)
    assert pdf_fixture().startswith(b"%PDF-") and pdf_fixture().rstrip().endswith(b"%%EOF")
    for bad in (ACTIVE_DB, TEST_DB, SOURCE_DB, "external_db"):
        try:
            no_active_target(SOURCE_DB, bad)
        except ValueError:
            pass
        else:
            raise AssertionError("Guard accepted unsafe restore target")
    probe = "a" * 64
    digests = {"columns": "A", "indexes": "B", "constraints": "C", "migrations": "D", "migration_count": 20}
    fixture = {"names": ["migrations", "sgp_rel_verify_probe"] + ["t" + str(i) for i in range(11)],
               "counts": {"migrations": 20, "sgp_rel_verify_probe": 1}, "digests": digests,
               "probe_sha256": hashlib.sha256(probe.encode()).hexdigest()}
    assert compare(fixture, fixture, probe)["all_pass"]
    other = {**fixture, "probe_sha256": "0" * 64}
    assert not compare(fixture, other, probe)["all_pass"]
    print("[SELF-TEST PASS] DB guards, positive/negative comparison, and synthetic PDF fixture")


def main() -> int:
    parser = argparse.ArgumentParser(description="One-shot disposable MariaDB A-25 logical rehearsal; safe dry-run by default")
    parser.add_argument("--run", action="store_true", help="Explicitly create TWO absent disposable DBs and exercise dump/restore; never drops DB")
    parser.add_argument("--self-test", action="store_true", help="Check built-in safety guards only, no DB access")
    parser.add_argument("--project", default=".", help="Laravel root; default current working directory")
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return 0
    project = Path(args.project).resolve()
    print("REL-VERIFY-01D / A-25 — disposable native MariaDB logical restore rehearsal")
    no_active_target(SOURCE_DB, RESTORE_DB)
    try:
        git_guard(project)
    except Exception as e:
        print("[STOP] Preflight: " + str(e))
        return 2
    print(f"[PLAN] Only disposable {SOURCE_DB} -> {RESTORE_DB} on {HOST}:{PORT}.")
    print("[PLAN] Migrate app schema on dummy source; synthetic marker only; dump/import/compare; run 01B synthetic parity.")
    print("[PLAN] Never reads user rows, never touches DB 'siapegawai' or 'siapegawai_testing', never DROP/overwrite.")
    print("[LIMIT] Does not complete real restored-app login/PDF UAT, real private-media parity, isolated-host recovery or owner sign-off.")
    if not args.run:
        print("[DRY RUN] No connection, files or DB modified. Use --run only after confirming these limitations.")
        return 0
    cred_dir = None
    run_dir = None
    stage = "starting"
    partial = {"work_id":"REL-VERIFY-01D", "gate":"A-25", "status":"IN_PROGRESS", "scope":"synthetic logical restore rehearsal only", "observed_wib":stamp()}
    try:
        pw = getpass.getpass("MariaDB Native root password (input hidden, not logged): ")
        cred_dir = private_options_dir()
        cred = option_file(cred_dir, pw)
        php_secret = cred_dir / "guarded-migrate-only.json"
        php_secret.write_text(json.dumps({"user":"root", "password":pw}), encoding="utf-8")
        del pw
        stage = "database preflight"
        db_preflight(cred)
        base = Path(os.environ["LOCALAPPDATA"]) / "Siapegawai" / "REL-VERIFY-01D-A25"
        base.mkdir(parents=True, exist_ok=True)
        run_dir = base / stamp()
        run_dir.mkdir(exist_ok=False)
        partial["local_run_folder"] = "PRIVATE_OUTSIDE_REPO"
        stage = "create disposable source"
        mysql_query(cred, f"CREATE DATABASE {sql_ident(SOURCE_DB)} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "create dummy source")
        print("[PASS] Created disposable SOURCE DB only; no active DB changes")
        stage = "migrate dummy source"
        migration_guard = run_dir / "guarded-dummy-migrate.php"
        migration_guard.write_text(migration_guard_php(), encoding="utf-8")
        try:
            extra_env = os.environ.copy()
            extra_env["DB_DATABASE"] = SOURCE_DB
            run_cmd([str(PHP), str(migration_guard), str(project), str(php_secret)], title="guarded Laravel migration", env=extra_env)
        finally:
            migration_guard.unlink(missing_ok=True)
        print("[PASS] Laravel migration command exited successfully on dummy SOURCE only")
        stage = "seed synthetic probe"
        marker = secrets.token_hex(32)
        fixture_init(cred, marker)
        stage = "dump disposable source"
        dump_file = run_dir / "source-synthetic.sql"
        dump_args = [str(DUMP), "--defaults-extra-file=" + str(cred), "--single-transaction", "--quick",
                     "--routines", "--triggers", "--skip-add-drop-table", "--skip-add-locks",
                     "--skip-lock-tables", "--no-create-db", "--default-character-set=utf8mb4", SOURCE_DB]
        with dump_file.open("xb") as out:
            run_cmd(dump_args, title="mysqldump synthetic staging source", output_file=out)
        dump_safe(dump_file)
        dump_sha = sha_file(dump_file)
        partial["dump_sha256"] = dump_sha
        partial["dump_bytes"] = dump_file.stat().st_size
        stage = "create isolated empty restore target"
        mysql_query(cred, f"CREATE DATABASE {sql_ident(RESTORE_DB)} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "create dummy restore")
        print("[PASS] Created NEW empty restore target; no DROP/overwrite")
        stage = "independently import dump"
        start = time.perf_counter()
        with dump_file.open("rb") as inp:
            run_cmd(mysql_args(cred, "--database=" + RESTORE_DB, "--binary-mode"),
                    title="import dump to disposable target", input_file=inp)
        restore_seconds = round(time.perf_counter() - start, 3)
        partial["logical_import_seconds"] = restore_seconds
        print(f"[PASS] SQL dump imported into isolated target; import_seconds={restore_seconds}")
        stage = "read-only inventory comparison"
        a = snapshot(cred, SOURCE_DB)
        b = snapshot(cred, RESTORE_DB)
        comparison = compare(a, b, marker)
        partial["comparison"] = comparison
        if not comparison["all_pass"]:
            raise RuntimeError("Schema/count/migration/synthetic-content comparison flagged mismatch")
        print(f"[PASS] Structure/indices/FKs/migrations/counts/synthetic content; {comparison['tables_source']} tables")
        stage = "run existing 01B private parity checker on synthetic PDF"
        partial["private_file_parity"] = parity(project, dump_file, run_dir)
        partial["status"] = "PASS_SYNTHETIC_LOGICAL_REHEARSAL_ONLY"
        partial["gate_A25"] = "OPEN"
        partial["not_tested"] = ["real staging data snapshot", "actual private PDF backup", "restored-app login/role/UAT",
                                 "independent host/server failure recovery", "real RTO/RPO", "reviewer/owner sign-off"]
        stage = "done"
        print("[RESULT] PASS_SYNTHETIC_LOGICAL_REHEARSAL_ONLY — gate A-25 remains OPEN for real-data-free app UAT and review")
        return 0
    except KeyboardInterrupt:
        partial["status"] = "INTERRUPTED"
        print("[STOP] Interrupted; disposable databases may remain. Script never drops or overwrites them.")
        return 130
    except Exception as e:
        partial["status"] = "BLOCKED_OR_FAILED"
        print("[STOP] " + stage + ": " + str(e))
        print("[NOTE] Disposable DBs may remain; do not retry into existing DBs or drop anything without inspection.")
        return 3
    finally:
        if cred_dir is not None:
            shutil.rmtree(cred_dir, ignore_errors=True)
        if run_dir is not None:
            evidence_dir = project / REPO_OUT
            try:
                evidence_dir.mkdir(parents=True, exist_ok=True)
                (evidence_dir / "SGP-REL-VERIFY-01D-A25-REHEARSAL-RESULT.json").write_text(
                    json.dumps(partial, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
                print("[EVIDENCE] Redacted summary saved under ignored .siapegawai-backup/rel-verify-01d/a25/")
            except OSError:
                print("[WARN] Could not write redacted summary; keep local terminal transcript safely")


if __name__ == "__main__":
    sys.exit(main())
