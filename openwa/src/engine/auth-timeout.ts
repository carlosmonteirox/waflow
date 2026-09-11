/**
 * Optional initial engine boot timeout. Kept dependency-free so Baileys-only
 * deployments can use the session watchdog without loading whatsapp-web.js.
 */
export function resolveAuthTimeoutMs(): number | undefined {
  const raw = process.env.WWEBJS_AUTH_TIMEOUT_MS?.trim();
  if (!raw || !/^\d+$/.test(raw)) {
    return undefined;
  }

  const ms = Number(raw);
  return Number.isSafeInteger(ms) && ms > 0 ? ms : undefined;
}
