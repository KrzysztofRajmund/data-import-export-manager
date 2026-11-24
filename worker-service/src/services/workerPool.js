import { Worker } from 'node:worker_threads';
import { fileURLToPath } from 'node:url';

const workerUrl = fileURLToPath(new URL('../workers/batchProcessor.worker.js', import.meta.url));

export class WorkerPool {
  constructor(size = 2) {
    this.workers = [];
    this.queue = [];
    for (let index = 0; index < size; index += 1) this.workers.push({ worker: new Worker(workerUrl), busy: false });
  }

  run(payload) {
    return new Promise((resolve, reject) => {
      const task = { payload, resolve, reject };
      const available = this.workers.find((entry) => !entry.busy);
      if (available) this.execute(available, task);
      else this.queue.push(task);
    });
  }

  execute(entry, task) {
    entry.busy = true;
    const onMessage = (result) => {
      cleanup();
      entry.busy = false;
      task.resolve(result);
      this.next();
    };
    const onError = (error) => {
      cleanup();
      entry.busy = false;
      task.reject(error);
      this.next();
    };
    const cleanup = () => {
      entry.worker.off('message', onMessage);
      entry.worker.off('error', onError);
    };
    entry.worker.once('message', onMessage);
    entry.worker.once('error', onError);
    entry.worker.postMessage(task.payload);
  }

  next() {
    if (this.queue.length === 0) return;
    const available = this.workers.find((entry) => !entry.busy);
    if (available) this.execute(available, this.queue.shift());
  }

  async close() {
    await Promise.all(this.workers.map((entry) => entry.worker.terminate()));
  }
}
