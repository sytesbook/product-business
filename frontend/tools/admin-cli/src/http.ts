import type { HttpResult } from './types.js';

export async function request(method: string, url: string, body?: unknown): Promise<HttpResult> {
  try {
    const hasBody = body !== undefined;
    const response = await fetch(url, {
      method,
      headers: hasBody ? { 'Content-Type': 'application/json' } : {},
      body: hasBody ? JSON.stringify(body) : undefined,
    });

    let data: unknown;
    const contentType = response.headers.get('content-type') ?? '';
    if (contentType.includes('application/json')) {
      try {
        data = (await response.json()) as unknown;
      } catch {
        data = null;
      }
    } else {
      const text = await response.text();
      data = text || null;
    }

    return { ok: response.ok, status: response.status, data };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    return { ok: false, status: 0, data: { message: `Network error: ${message}` } };
  }
}
