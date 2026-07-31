import express from "express";
import path from "path";
import { fileURLToPath } from "url";
import dotenv from "dotenv";
import { readFile, writeFile, mkdir } from "fs/promises";
import helmet from "helmet";
import rateLimit from "express-rate-limit";

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

const FB_PAGE_ID = process.env.FACEBOOK_PAGE_ID;
const FB_PAGE_ACCESS_TOKEN = process.env.FACEBOOK_PAGE_ACCESS_TOKEN;
const GRAPH_VERSION = process.env.FACEBOOK_GRAPH_VERSION || "v20.0";
const REFRESH_INTERVAL_MS = 10 * 60 * 1000;
const MAX_VIDEOS = 8;

const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || "";
const CONTENT_FILE = path.join(__dirname, "data", "content.json");

if (!FB_PAGE_ID || !FB_PAGE_ACCESS_TOKEN) {
  console.warn(
    "[varovani] FACEBOOK_PAGE_ID nebo FACEBOOK_PAGE_ACCESS_TOKEN neni nastaveno v .env — endpoint /api/videos bude vracet prazdnou cache, dokud promenne nedoplnis."
  );
}

if (!ADMIN_PASSWORD) {
  console.warn(
    "[varovani] ADMIN_PASSWORD neni nastaveno v .env — admin panel nebude fungovat, dokud promennou nedoplnis."
  );
}

// jednoducha in-memory cache — zadna databaze neni potreba pro par videi
let cache = {
  videos: [],
  lastUpdated: null,
  lastError: null,
};

async function fetchVideosFromFacebook() {
  if (!FB_PAGE_ID || !FB_PAGE_ACCESS_TOKEN) return;

  const fields = [
    "id",
    "title",
    "description",
    "permalink_url",
    "created_time",
    "thumbnails.limit(1){uri}",
  ].join(",");

  const url = `https://graph.facebook.com/${GRAPH_VERSION}/${FB_PAGE_ID}/videos?fields=${encodeURIComponent(
    fields
  )}&limit=${MAX_VIDEOS}&access_token=${FB_PAGE_ACCESS_TOKEN}`;

  try {
    const res = await fetch(url);
    const data = await res.json();

    if (data.error) {
      throw new Error(`Graph API error: ${data.error.message} (code ${data.error.code})`);
    }

    const videos = (data.data || []).map((v) => ({
      id: v.id,
      title: v.title || v.description?.slice(0, 80) || "Video z Facebooku",
      permalinkUrl: v.permalink_url
        ? `https://www.facebook.com${v.permalink_url.startsWith("/") ? "" : "/"}${v.permalink_url}`
        : null,
      thumbnail: v.thumbnails?.data?.[0]?.uri || null,
      createdTime: v.created_time || null,
    }));

    cache = { videos, lastUpdated: new Date().toISOString(), lastError: null };
    console.log(`[fb-sync] Nacteno ${videos.length} videi (${cache.lastUpdated})`);
  } catch (err) {
    cache.lastError = err.message;
    console.error("[fb-sync] Chyba pri stahovani videi:", err.message);
  }
}

fetchVideosFromFacebook();
setInterval(fetchVideosFromFacebook, REFRESH_INTERVAL_MS);

async function readContent() {
  try {
    const raw = await readFile(CONTENT_FILE, "utf-8");
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

async function writeContent(data) {
  await mkdir(path.dirname(CONTENT_FILE), { recursive: true });
  await writeFile(CONTENT_FILE, JSON.stringify(data, null, 2), "utf-8");
}

function requireAdminPassword(req, res, next) {
  if (!ADMIN_PASSWORD) {
    return res.status(503).json({ error: "ADMIN_PASSWORD není nastaveno v .env." });
  }
  const pw = req.headers["x-admin-password"] || "";
  if (pw !== ADMIN_PASSWORD) {
    return res.status(401).json({ error: "Nesprávné heslo." });
  }
  next();
}

app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      styleSrc: ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com"],
      fontSrc: ["'self'", "https://fonts.gstatic.com"],
      imgSrc: ["'self'", "data:", "https://"],
      connectSrc: ["'self'"],
    },
  },
}));

const adminLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 20,
  message: { error: "Příliš mnoho požadavků, zkuste to za 15 minut." },
  standardHeaders: true,
  legacyHeaders: false,
});

app.use(express.json({ limit: "50kb" }));
app.use(express.static(path.join(__dirname, "public")));

// --- Videos API ---

app.get("/api/videos", (req, res) => {
  res.json({
    videos: cache.videos,
    lastUpdated: cache.lastUpdated,
    error: cache.lastError,
  });
});

app.post("/api/videos/refresh", async (req, res) => {
  await fetchVideosFromFacebook();
  res.json({ videos: cache.videos, lastUpdated: cache.lastUpdated, error: cache.lastError });
});

// --- Content API ---

app.get("/api/content", async (req, res) => {
  const content = await readContent();
  if (!content) return res.status(404).json({ error: "Obsah nenalezen." });
  res.json(content);
});

app.post("/api/content", adminLimiter, requireAdminPassword, async (req, res) => {
  const body = req.body;
  if (!body || typeof body !== "object" || Array.isArray(body)) {
    return res.status(400).json({ error: "Neplatný formát dat." });
  }
  try {
    await writeContent(body);
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// --- Admin auth verify ---

app.get("/api/admin/verify", adminLimiter, requireAdminPassword, (req, res) => {
  res.json({ ok: true });
});

app.use((req, res) => {
  res.status(404).sendFile(path.join(__dirname, "public", "404.html"));
});

app.listen(PORT, () => {
  console.log(`Harlej Revival web bezi na http://localhost:${PORT}`);
  console.log(`Admin panel:           http://localhost:${PORT}/admin.html`);
});
