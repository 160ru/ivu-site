// Сборка новостной ленты для сайта.
//
// Что делает:
//   1. берёт ваши записи из data/news-manual.json (они всегда сверху);
//   2. читает RSS-ленты Хабрика по темам: ИТ-новости, 1С, информационная безопасность;
//   3. убирает дубли, сортирует по дате, оставляет 6 последних;
//   4. пишет data/news.json — его и читает страница.
//
// Запуск:  node scripts/update-news.mjs
// Автоматически запускается GitHub Actions по расписанию (см. .github/workflows/news.yml).

import { readFile, writeFile, mkdir } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");
const MANUAL_PATH = join(ROOT, "data", "news-manual.json");
const OUT_PATH = join(ROOT, "data", "news.json");

const FEEDS = [
  { url: "https://habr.com/ru/rss/hub/1c/?fl=ru", source: "Хабр · 1С", limit: 2 },
  { url: "https://habr.com/ru/rss/hub/infosecurity/?fl=ru", source: "Хабр · Безопасность", limit: 2 },
  { url: "https://habr.com/ru/rss/news/?fl=ru", source: "Хабр", limit: 2 }
];

const MAX_ITEMS = 6;

// Служебные и нежелательные заголовки в ленту не попадают
const BLOCKED = /(говн|хуй|хуё|хер|пизд|ебан|ебёт|сук[аи]|бляд|нахуй|жоп|дерьм|срач|порн)/i;

function decode(text) {
  return String(text)
    .replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, "$1")
    .replace(/<[^>]+>/g, "")
    .replace(/&laquo;/g, "«").replace(/&raquo;/g, "»")
    .replace(/&mdash;/g, "—").replace(/&ndash;/g, "–")
    .replace(/&quot;/g, '"').replace(/&#39;/g, "'")
    .replace(/&nbsp;/g, " ").replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<").replace(/&gt;/g, ">")
    .replace(/\s+/g, " ")
    .trim();
}

function pick(block, tag) {
  const m = block.match(new RegExp("<" + tag + "[^>]*>([\\s\\S]*?)</" + tag + ">", "i"));
  return m ? decode(m[1]) : "";
}

function parseFeed(xml, source, limit) {
  const items = [];
  const chunks = xml.match(/<item[\s\S]*?<\/item>/gi) || [];
  for (const chunk of chunks) {
    if (items.length >= limit) break;
    const title = pick(chunk, "title");
    const link = pick(chunk, "link") || (chunk.match(/<guid[^>]*>([\s\S]*?)<\/guid>/i) || [])[1];
    const date = pick(chunk, "pubDate") || pick(chunk, "dc:date");
    if (!title || !link) continue;
    if (BLOCKED.test(title)) continue;
    const parsed = date ? Date.parse(date) : Date.now();
    items.push({
      title,
      url: link.trim(),
      date: new Date(Number.isNaN(parsed) ? Date.now() : parsed).toISOString().slice(0, 10),
      source,
      manual: false
    });
  }
  return items;
}

async function loadManual() {
  try {
    const raw = await readFile(MANUAL_PATH, "utf8");
    const data = JSON.parse(raw);
    const list = Array.isArray(data) ? data : data.items || [];
    return list
      .filter((item) => item && item.title)
      .map((item) => ({
        title: item.title,
        url: item.url || "",
        date: item.date || new Date().toISOString().slice(0, 10),
        source: item.source || "Интер-Техника+",
        manual: true
      }));
  } catch {
    return [];
  }
}

async function main() {
  const manual = await loadManual();
  const collected = [];

  for (const feed of FEEDS) {
    try {
      const res = await fetch(feed.url, {
        headers: { "User-Agent": "ivu-site-news/1.0 (+https://ivu.ru)" },
        signal: AbortSignal.timeout(20000)
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      collected.push(...parseFeed(await res.text(), feed.source, feed.limit || 2));
    } catch (error) {
      console.warn("лента недоступна:", feed.url, "—", error.message);
    }
  }

  const seen = new Set();
  const items = [...manual, ...collected]
    .filter((item) => {
      const key = (item.url || item.title).toLowerCase();
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    })
    .sort((a, b) => {
      if (a.manual !== b.manual) return a.manual ? -1 : 1;
      return b.date.localeCompare(a.date);
    })
    .slice(0, MAX_ITEMS);

  await mkdir(dirname(OUT_PATH), { recursive: true });
  await writeFile(
    OUT_PATH,
    JSON.stringify({ generated: new Date().toISOString(), items }, null, 2) + "\n",
    "utf8"
  );
  console.log("news.json: " + items.length + " записей, из них своих — " + manual.length);
}

main().catch((error) => {
  console.error("не удалось собрать новости:", error);
  process.exitCode = 1;
});
