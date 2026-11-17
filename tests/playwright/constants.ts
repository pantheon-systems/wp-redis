// Must precede any process.env read below; importers cannot be relied on to
// have loaded .env first.
import 'dotenv/config';
import path from 'path';

export const AUTH_FILE = path.join(__dirname, '.auth/admin.json');

// Allowlisted CI User-Agent, supplied as a secret. Apply after any devices
// spread, which sets a userAgent of its own.
export const BROWSER_UA = process.env.CI_UA ? { userAgent: process.env.CI_UA } : {};
