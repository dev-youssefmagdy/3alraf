import pino, { type DestinationStream, type Logger } from 'pino';

const SENSITIVE_FIELDS = [
  'authorization',
  'cookie',
  'token',
  'access_token',
  'refresh_token',
  'bearer',
  'password',
  'secret',
  'client_secret',
  'service_token',
  'serviceToken',
  'bearerToken',
  'ownerPassword',
  'code_verifier',
];

/** pino redact paths: each sensitive field at the top level, one level deep, and under headers. */
export const REDACT_PATHS = SENSITIVE_FIELDS.flatMap((f) => [
  f,
  `*.${f}`,
  `headers.${f}`,
  `req.headers.${f}`,
  `res.headers.${f}`,
]);

export function createLogger(level: string, destination?: DestinationStream): Logger {
  const options = {
    level,
    base: { service: '3alraf-mcp' },
    timestamp: pino.stdTimeFunctions.isoTime,
    redact: { paths: REDACT_PATHS, censor: '[REDACTED]' },
  };
  return destination ? pino(options, destination) : pino(options);
}
