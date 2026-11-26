// Локальный сервер для просмотра сайта. Без зависимостей — только Node.js.
// Запуск: npm run dev   (или: node server.mjs)
// Порт меняется переменной окружения: $env:PORT=4000; npm run dev

import { createServer } from "node:http";
import { createReadStream, existsSync, statSync } from "node:fs";
import { extname, join, normalize, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = resolve(fileURLToPath(new URL(".", import.meta.url)));
const PORT = Number(process.env.PORT) || 3000;

const TYPES = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".mjs": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".webp": "image/webp",
  ".ico": "image/x-icon",
  ".woff2": "font/woff2",
  ".txt": "text/plain; charset=utf-8",
  ".xml": "application/xml; charset=utf-8"
};

const server = createServer((req, res) => {
  let url = "/";
  try {
    url = decodeURIComponent((req.url || "/").split("?")[0]);
  } catch {
    res.writeHead(400).end("Bad request");
    return;
  }

  let filePath = normalize(join(ROOT, url));

  // Не выпускаем запросы за пределы папки сайта
  if (filePath !== ROOT && !filePath.startsWith(ROOT + sep)) {
    res.writeHead(403, { "Content-Type": "text/plain; charset=utf-8" }).end("403: за пределами сайта");
    return;
  }

  if (existsSync(filePath) && statSync(filePath).isDirectory()) {
    filePath = join(filePath, "index.html");
  }

  if (!existsSync(filePath)) {
    const page = join(ROOT, "404.html");
    res.writeHead(404, { "Content-Type": "text/html; charset=utf-8" });
    if (existsSync(page)) createReadStream(page).pipe(res);
    else res.end("404: страница не найдена");
    return;
  }

  res.writeHead(200, {
    "Content-Type": TYPES[extname(filePath).toLowerCase()] || "application/octet-stream",
    "Cache-Control": "no-cache"
  });
  createReadStream(filePath).pipe(res);
});

server.listen(PORT, () => {
  console.log(`ivu-site запущен: http://localhost:${PORT}`);
  console.log("Остановить: Ctrl+C");
});
