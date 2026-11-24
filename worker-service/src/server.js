import http from 'node:http';
import { WorkerPool } from './services/workerPool.js';

const host = process.env.WORKER_HOST || '127.0.0.1';
const port = Number(process.env.WORKER_PORT || 3001);
const apiKey = process.env.WORKER_API_KEY || 'local-worker-secret';
const workerCount = Math.max(1, Number(process.env.WORKER_COUNT || 2));
const pool = new WorkerPool(workerCount);

function sendJson(response, status, payload) {
  const body = JSON.stringify(payload);
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
  });
  response.end(body);
}

async function readJson(request) {
  let body = '';
  for await (const chunk of request) {
    body += chunk;
    if (Buffer.byteLength(body) > 10 * 1024 * 1024) throw new Error('Request body too large');
  }
  return JSON.parse(body || '{}');
}

const server = http.createServer(async (request, response) => {
  if (request.method !== 'POST' || request.url !== '/process-batch') {
    sendJson(response, 404, { ok: false, error: 'Not found' });
    return;
  }

  if (request.headers['x-api-key'] !== apiKey) {
    sendJson(response, 401, { ok: false, error: 'Unauthorized' });
    return;
  }

  try {
    const payload = await readJson(request);
    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    const batchSize = Math.max(100, Number(process.env.WORKER_BATCH_SIZE || 500));
    const validRows = [];
    const errors = [];

    for (let start = 0; start < rows.length; start += batchSize) {
      const batch = rows.slice(start, start + batchSize);
      const result = await pool.run({
        headers: payload.headers || [],
        mapping: payload.mapping || {},
        rows: batch,
        headerRow: Number(payload.headerRow || 1),
        columns: payload.columns || [],
        batchOffset: start + Number(payload.batchOffset || 0),
      });
      validRows.push(...(result.validRows || []));
      errors.push(...(result.errors || []));
    }

    sendJson(response, 200, {
      ok: true,
      result: {
        validRows,
        errors,
        totalRows: rows.length,
        importedRows: validRows.length,
        failedRows: rows.length - validRows.length,
      },
    });
  } catch (error) {
    sendJson(response, 400, { ok: false, error: error instanceof Error ? error.message : 'Invalid request' });
  }
});

server.listen(port, host, () => {
  console.log(`Worker service listening on http://${host}:${port}`);
  console.log(`Worker threads: ${workerCount}`);
});

async function shutdown() {
  server.close(async () => {
    await pool.close();
    process.exit(0);
  });
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
