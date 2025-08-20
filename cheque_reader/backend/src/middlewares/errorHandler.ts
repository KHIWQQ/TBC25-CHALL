import { Request, Response, NextFunction } from 'express';

export function errorHandler(err: any, req: Request, res: Response, next: NextFunction) {
  console.error('[ERROR]', err);
  res.status(500).json({ success: false, message: 'Internal server error', timestamp: new Date().toISOString() });
} 