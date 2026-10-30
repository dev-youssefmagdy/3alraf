process.env.WA_API_TOKEN = 'test-token';
process.env.WA_CALLBACK_URL = 'http://127.0.0.1:9/none';
process.env.WA_CALLBACK_SECRET = 'secret';
process.env.WA_AUTH_DIR = require('fs').mkdtempSync(require('path').join(require('os').tmpdir(), 'wa-'));
process.env.WA_ENCRYPTION_KEY = require('crypto').randomBytes(32).toString('base64');
