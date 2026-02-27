const http = require('http');
const fs = require('fs/promises');
const path = require('path');

const HOST = '0.0.0.0';
const PORT = Number(process.env.PORT) || 3000;
const ROOT_DIR = __dirname;
const DATA_FILE = path.join(ROOT_DIR, 'data.json');

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.svg': 'image/svg+xml'
};

async function readData() {
  const raw = await fs.readFile(DATA_FILE, 'utf8');
  return JSON.parse(raw);
}

async function writeData(data) {
  await fs.writeFile(DATA_FILE, `${JSON.stringify(data, null, 2)}\n`, 'utf8');
}

function sendJson(res, statusCode, payload) {
  res.writeHead(statusCode, { 'Content-Type': MIME_TYPES['.json'] });
  res.end(JSON.stringify(payload));
}

async function parseBody(req) {
  let body = '';
  for await (const chunk of req) {
    body += chunk;
  }
  return body ? JSON.parse(body) : {};
}

function safeFilePath(urlPath) {
  const cleanPath = urlPath === '/' ? '/index.html' : urlPath;
  const resolved = path.resolve(ROOT_DIR, `.${cleanPath}`);
  if (!resolved.startsWith(ROOT_DIR)) {
    return null;
  }
  return resolved;
}

async function handleApi(req, res) {
  if (req.method === 'GET' && req.url === '/api/data') {
    const data = await readData();
    return sendJson(res, 200, data);
  }

  if (req.method === 'POST' && req.url === '/api/orders') {
    const body = await parseBody(req);
    const data = await readData();

    if (!body || typeof body.orders !== 'object') {
      return sendJson(res, 400, { error: 'Nieprawidłowe dane zamówień.' });
    }

    data.orders = body.orders;
    await writeData(data);
    return sendJson(res, 200, { ok: true });
  }

  if (req.method === 'POST' && req.url === '/api/users') {
    const body = await parseBody(req);
    const data = await readData();

    const name = String(body.name || '').trim();
    const password = String(body.password || '').trim();
    const deviceIds = Array.isArray(body.deviceIds) ? body.deviceIds : [];

    if (!name || !password) {
      return sendJson(res, 400, { error: 'Podaj nazwę i hasło użytkownika.' });
    }

    const exists = data.users.some((user) => user.name.toLowerCase() === name.toLowerCase());
    if (exists) {
      return sendJson(res, 409, { error: 'Użytkownik już istnieje.' });
    }

    data.users.push({ name, password });
    data.orders[name] = Object.fromEntries(deviceIds.map((id) => [id, 0]));

    await writeData(data);
    return sendJson(res, 201, { ok: true, user: { name, password } });
  }

  sendJson(res, 404, { error: 'Nie znaleziono endpointu API.' });
}

const server = http.createServer(async (req, res) => {
  try {
    if (!req.url) {
      sendJson(res, 400, { error: 'Brak URL.' });
      return;
    }

    if (req.url.startsWith('/api/')) {
      await handleApi(req, res);
      return;
    }

    const target = safeFilePath(req.url.split('?')[0]);
    if (!target) {
      res.writeHead(403);
      res.end('Forbidden');
      return;
    }

    const ext = path.extname(target).toLowerCase();
    const contentType = MIME_TYPES[ext] || 'application/octet-stream';
    const file = await fs.readFile(target);

    res.writeHead(200, { 'Content-Type': contentType });
    res.end(file);
  } catch (error) {
    if (error && error.code === 'ENOENT') {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('Nie znaleziono pliku');
      return;
    }

    sendJson(res, 500, { error: 'Błąd serwera', details: error.message });
  }
});

server.listen(PORT, HOST, () => {
  console.log(`Serwer działa na http://${HOST}:${PORT}`);
});
