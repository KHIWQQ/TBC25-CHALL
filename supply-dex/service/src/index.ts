import { Hono } from "hono";
import { serveStatic } from "hono/bun";
import { serve } from "bun";
import { readFile } from "node:fs/promises";
import { existsSync, mkdirSync } from "node:fs";
import path from "node:path";
import {
  createPublicClient,
  createWalletClient,
  http,
  parseAbiItem,
  formatEther,
  parseEther,
} from "viem";
import { foundry } from "viem/chains";
import {
  generatePrivateKey,
  mnemonicToAccount,
  privateKeyToAccount,
} from "viem/accounts";
import { importSPKI, jwtVerify } from "jose";
import type { JWTPayload } from "jose";
import { getCookie, setCookie } from "hono/cookie";
import { Database } from "bun:sqlite"; // ⬅️ NEW

const RPC_URL = "http://localhost:8545";
const OUT_DIR = "/app/data";
const INST_PATH = path.join(OUT_DIR, "instance.json");
const DB_PATH = path.join(OUT_DIR, "db.sqlite"); // ⬅️ NEW
const FUND_SESSION_ETH = 10;
const RATE_WINDOW_MS = 60_000;
const RATE_MAX = 3;

const JWT_AUD = "supp-dex";
const JWT_ISS_CHECKER = "checker";
const CHECKER_JWT_PUB = `-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAZZS47JYLkxvSU6MHQyyfjQyH1OjVWXNKkEmxJboRmA0=
-----END PUBLIC KEY-----`;

const MNEMONIC = process.env.MNEMONIC || "";
const ACCOUNT_INDEX = Number(process.env.DEPLOYER_INDEX ?? 0);
const DEPLOYER_PK = process.env.DEPLOYER_PK as `0x${string}` | undefined;

if (!MNEMONIC) {
  console.error("[api] Missing MNEMONIC. entrypoint.sh must export it.");
  process.exit(1);
}
const deployerAccount = mnemonicToAccount(MNEMONIC, {
  accountIndex: ACCOUNT_INDEX,
});

// ---- viem clients
const publicClient = createPublicClient({
  chain: foundry,
  transport: http(RPC_URL),
});
const deployerWallet = createWalletClient({
  account: deployerAccount,
  chain: foundry,
  transport: http(RPC_URL),
});

// ---- types
type Deployment = {
  deployer: `0x${string}`;
  setup: `0x${string}`;
  proxy: `0x${string}`;
  rescue: `0x${string}`;
};

type SessionWallet = {
  address: `0x${string}`;
  privateKey: `0x${string}`;
};

type SessionData = { created: number; wallet?: SessionWallet };

let deployment: Deployment;
const sessions = new Map<string, SessionData>();
const rate = new Map<string, { windowStart: number; count: number }>();
let checkerKey: CryptoKey | null = null;

function ensureDir() {
  if (!existsSync(OUT_DIR)) mkdirSync(OUT_DIR, { recursive: true });
}

async function hasCode(addr: `0x${string}`) {
  const code = await publicClient
    .getCode({ address: addr })
    .catch(() => undefined);
  return !!code && code.length > 2;
}

async function verifyDeploymentOrFail(d: Deployment) {
  const okSetup = await hasCode(d.setup);
  const okProxy = await hasCode(d.proxy);
  if (!okSetup || !okProxy) {
    throw new Error(
      `stale deployment: ${!okSetup ? "setup" : ""} ${
        !okProxy ? "proxy" : ""
      } has no bytecode. ` +
        `Anvil likely restarted; entrypoint must (re)deploy before API starts.`
    );
  }
}

async function loadDeployment(): Promise<Deployment> {
  ensureDir();
  if (!existsSync(INST_PATH)) {
    throw new Error(
      `instance.json not found at ${INST_PATH}. Did entrypoint run forge?`
    );
  }
  return JSON.parse(await readFile(INST_PATH, "utf8")) as Deployment;
}

async function fund(address: `0x${string}`, eth: number) {
  const tx = await deployerWallet.sendTransaction({
    to: address,
    value: parseEther(eth.toString()),
  });
  await publicClient.waitForTransactionReceipt({ hash: tx });
}

let db: Database;
let stmtInsertFlag: ReturnType<Database["prepare"]>;
let stmtGetFlag: ReturnType<Database["prepare"]>;
let stmtDeleteFlag: ReturnType<Database["prepare"]>;
let stmtCountFlags: ReturnType<Database["prepare"]>;
let stmtAllFlags: ReturnType<Database["prepare"]>;
let stmtLatestId: ReturnType<Database["prepare"]>;

function initDb() {
  db = new Database(DB_PATH, { create: true });
  db.exec(`
    PRAGMA journal_mode=WAL;
    PRAGMA synchronous=NORMAL;
    CREATE TABLE IF NOT EXISTS flags (
      id TEXT PRIMARY KEY,
      flag TEXT NOT NULL,
      created_at INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_flags_created_at ON flags(created_at);
  `);

  stmtInsertFlag = db.prepare(`
    INSERT INTO flags (id, flag, created_at)
    VALUES (?1, ?2, ?3)
    ON CONFLICT(id) DO UPDATE SET
      flag=excluded.flag,
      created_at=excluded.created_at
  `);
  stmtGetFlag = db.prepare(`SELECT flag FROM flags WHERE id = ?1`);
  stmtDeleteFlag = db.prepare(`DELETE FROM flags WHERE id = ?1`);
  stmtCountFlags = db.prepare(`SELECT COUNT(*) AS c FROM flags`);
  stmtAllFlags = db.prepare(
    `SELECT id, flag FROM flags ORDER BY created_at ASC`
  );
  stmtLatestId = db.prepare(
    `SELECT id FROM flags ORDER BY created_at DESC LIMIT 1`
  );
}

function latestFlagId(): string | null {
  const row = stmtLatestId.get() as { id?: string } | undefined;
  return row?.id ?? null;
}

function getFlag(id: string) {
  const row = stmtGetFlag.get(id) as { flag?: string } | undefined;
  return row?.flag ?? null;
}

function putFlag(id: string, flag: string) {
  stmtInsertFlag.run(id, flag, Date.now());
}

function putManyFlags(
  input: Array<{ id: string; flag: string }> | Record<string, string>
) {
  const tx = db.transaction((items: Array<[string, string]>) => {
    for (const [id, flag] of items) {
      stmtInsertFlag.run(id, flag, Date.now());
    }
  });

  const pairs: Array<[string, string]> = Array.isArray(input)
    ? input.map(({ id, flag }) => [String(id), String(flag)])
    : Object.entries(input).map(([id, flag]) => [String(id), String(flag)]);

  tx(pairs);
}

function popFlagById(id: string) {
  const info = stmtDeleteFlag.run(id);
  // bun:sqlite returns { changes, lastInsertRowid }
  // @ts-ignore
  return (info?.changes ?? 0) > 0;
}

function countFlags(): number {
  const row = stmtCountFlags.get() as { c: number };
  return row.c ?? 0;
}

function allFlags(): Array<{ id: string; flag: string }> {
  return stmtAllFlags.all() as Array<{ id: string; flag: string }>;
}

type Vars = { sid: string; session: SessionData; jwt?: JWTPayload };
const app = new Hono<{ Variables: Vars }>();

const wrap = (fn: (c: any) => any) => (c: any) =>
  Promise.resolve(fn(c)).catch((e: any) =>
    c.json({ error: e?.message ?? "internal_error" }, 500)
  );

const session = () => async (c: any, next: any) => {
  let sid = getCookie(c, "sid") || c.req.header("x-session-id");
  if (!sid || !sessions.has(sid)) {
    sid = crypto.randomUUID();
    sessions.set(sid, { created: Date.now() });
    setCookie(c, "sid", sid, {
      httpOnly: true,
      sameSite: "Lax",
      path: "/",
      maxAge: 60 * 60 * 24 * 7,
    });
  }
  c.set("sid", sid);
  c.set("session", sessions.get(sid)!);
  await next();
};

const rateLimit =
  (max = RATE_MAX, winMs = RATE_WINDOW_MS) =>
  async (c: any, next: any) => {
    const sid = c.get("sid") as string;
    const ip =
      c.req.header("x-forwarded-for") ||
      c.req.header("cf-connecting-ip") ||
      "unknown";
    const key = `${sid}:${ip}`;
    const now = Date.now();
    const curr = rate.get(key);
    if (!curr) {
      rate.set(key, { windowStart: now, count: 1 });
      return next();
    }
    const elapsed = now - curr.windowStart;
    if (elapsed > winMs) {
      rate.set(key, { windowStart: now, count: 1 });
      return next();
    }
    if (curr.count >= max)
      return c.json(
        { error: "rate_limited", retryAfterMs: winMs - elapsed },
        429
      );
    curr.count++;
    return next();
  };

async function loadJwtKeys() {
  if (CHECKER_JWT_PUB) checkerKey = await importSPKI(CHECKER_JWT_PUB, "EdDSA");
}
const jwt = () => async (c: any, next: any) => {
  if (!checkerKey) await loadJwtKeys();
  if (!checkerKey) return c.json({ error: "auth key missing on server" }, 500);

  const auth = c.req.header("authorization") || "";
  if (!auth.startsWith("Bearer "))
    return c.json({ error: "missing bearer" }, 401);
  const token = auth.slice("Bearer ".length);
  try {
    const { payload } = await jwtVerify(token, checkerKey, {
      algorithms: ["EdDSA"],
      issuer: JWT_ISS_CHECKER,
      audience: JWT_AUD,
    });
    c.set("jwt", payload);
    await next();
  } catch (e: any) {
    return c.json({ error: e?.message ?? "unauthorized" }, 401);
  }
};

await (async () => {
  ensureDir();
  initDb(); // ⬅️ NEW
  deployment = await loadDeployment();
  await verifyDeploymentOrFail(deployment);
})();

app.get(
  "/_health",
  wrap(async (c) => c.json({ ok: true, deployment, flags: countFlags() }))
);

app.post(
  "/session",
  session(),
  wrap(async (c) => c.json({ ok: true, sid: c.get("sid") }))
);

app.post(
  "/wallet",
  session(),
  rateLimit(),
  wrap(async (c) => {
    const sid = c.get("sid") as string;
    const data = c.get("session") as SessionData;
    if (!data.wallet) {
      const pk = generatePrivateKey();
      const acct = privateKeyToAccount(pk);
      data.wallet = { address: acct.address, privateKey: pk };
      sessions.set(sid, data);
      await fund(acct.address as `0x${string}`, FUND_SESSION_ETH);
    }
    const funder = {
      address: deployerAccount.address,
      publicKey: (deployerAccount as any).publicKey ?? null,
      privateKey: DEPLOYER_PK,
    };
    return c.json({
      ok: true,
      sid,
      wallet: data.wallet,
      funder,
      deployment: { ...deployment, deployer: undefined as any },
    });
  })
);

app.get(
  "/wallet",
  session(),
  wrap(async (c) => {
    const sid = c.get("sid") as string;
    const data = c.get("session") as SessionData;
    return c.json({ ok: true, sid, wallet: data.wallet ?? null, deployment });
  })
);

// --- flags API (uses SQLite)

app.get(
  "/flags/:id",
  jwt(),
  wrap(async (c) => {
    const id = c.req.param("id");
    const flag = getFlag(id);
    if (flag === null) return c.json({ ok: false, error: "not found" }, 404);
    return c.json({ ok: true, id, flag });
  })
);

app.post(
  "/flags",
  jwt(),
  wrap(async (c) => {
    const body = await c.req.json();
    if (body?.id && body?.flag) {
      putFlag(String(body.id), String(body.flag));
      return c.json({ ok: true, count: countFlags() });
    }
    if (
      Array.isArray(body?.flags) ||
      (body?.flags && typeof body.flags === "object")
    ) {
      putManyFlags(body.flags);
      return c.json({ ok: true, count: countFlags() });
    }
    return c.json({ error: "invalid body" }, 400);
  })
);

app.get(
  "/flags/peek/:id",
  jwt(),
  wrap(async (c) => {
    const id = c.req.param("id");
    const flag = getFlag(id);
    if (flag === null) return c.json({ ok: false, error: "not found" }, 404);
    return c.json({ ok: true, id, flag });
  })
);

app.get(
  "/flags/count",
  jwt(),
  wrap(async (c) => c.json({ ok: true, count: countFlags() }))
);

// --- isSolved now returns ALL flags when solved
app.get(
  "/isSolved",
  wrap(async (c) => {
    const solved = await publicClient.readContract({
      address: deployment.setup,
      abi: [parseAbiItem("function isSolved() view returns (bool)")],
      functionName: "isSolved",
    });

    if (!solved) {
      return c.json({ ok: true, solved: false });
    }

    // return all flags (id + flag) in insertion order
    const flags = allFlags(); // [{id, flag}, ...]
    return c.json({
      ok: true,
      solved: true,
      flags,
    });
  })
);

app.get(
  "/deployment",
  wrap(async (c) => {
    const bal = await publicClient.getBalance({ address: deployment.proxy });
    return c.json({ ok: true, deployment, proxyBalanceETH: formatEther(bal) });
  })
);

app.use("/*", serveStatic({ root: "/app/service/public" }));
app.get("/", (c) => c.redirect("/index.html"));

serve({ fetch: app.fetch, port: 5000 });
