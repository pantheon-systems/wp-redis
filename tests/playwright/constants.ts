// Must precede any process.env read below; importers cannot be relied on to
// have loaded .env first.
import 'dotenv/config';
import path from 'path';

export const AUTH_FILE = path.join(__dirname, '.auth/admin.json');
