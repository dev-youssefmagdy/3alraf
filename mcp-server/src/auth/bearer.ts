import { createHash, timingSafeEqual } from 'node:crypto';
import type { NextFunction, Request, RequestHandler, Response } from 'express';
import type { AuthInfo } from '@modelcontextprotocol/sdk/server/auth/types.js';
import type {} from '@modelcontextprotocol/sdk/server/auth/middleware/bearerAuth.js'; // declares Request.auth
import type { Logger } from 'pino';

export function sha256(value: string): Buffer {
  return createHash('sha256').update(value).digest();
}

/** Constant-time for any input length: both sides are hashed to the same size first. */
export function safeEqual(a: string, b: string): boolean {
  return timingSafeEqual(sha256(a), sha256(b));
}

export interface TokenVerifier {
  verifyAccessToken(token: string): Promise<AuthInfo>;
}

export interface AuthOptions {
  staticToken?: string;
  oauth?: TokenVerifier;
  expectedResource?: string;
  resourceMetadataUrl?: string;
  logger: Logger;
}

const MAX_BEARER_LENGTH = 1024;

/** Extracts the raw credential. Only the Authorization header counts; tokens in query strings are never read. */
function parseBearer(header: string | undefined): string | undefined {
  if (!header || header.length > MAX_BEARER_LENGTH) return undefined;
  const match = /^Bearer +(\S+)$/i.exec(header);
  return match?.[1];
}

export function createAuthMiddleware(options: AuthOptions): RequestHandler {
  const challenge = (invalid: boolean): string => {
    const params = [
      options.resourceMetadataUrl ? `resource_metadata="${options.resourceMetadataUrl}"` : 'realm="3alraf-mcp"',
      ...(invalid ? ['error="invalid_token"'] : []),
    ];
    return `Bearer ${params.join(', ')}`;
  };

  const reject = (res: Response, invalid: boolean): void => {
    res.status(401).set('WWW-Authenticate', challenge(invalid)).json({ error: invalid ? 'invalid_token' : 'unauthorized' });
  };

  return async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    const presented = parseBearer(req.headers.authorization);
    if (!presented) {
      reject(res, false);
      return;
    }

    if (options.staticToken && safeEqual(presented, options.staticToken)) {
      res.locals['clientId'] = 'static-bearer';
      next();
      return;
    }

    if (options.oauth) {
      try {
        const info = await options.oauth.verifyAccessToken(presented);
        if (!options.expectedResource || info.resource?.href === options.expectedResource) {
          res.locals['clientId'] = `oauth:${info.clientId}`;
          req.auth = { ...info, token: '[REDACTED]' };
          next();
          return;
        }
      } catch {
        // fall through to the uniform 401
      }
    }

    options.logger.warn({ path: req.path }, 'rejected invalid bearer credential');
    reject(res, true);
  };
}
