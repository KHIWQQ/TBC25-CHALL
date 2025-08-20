export interface DepositCheckRequest {
  frontImage: File;
  backImage: File;
  amount: string;
  remarks?: string;
}

export interface DepositCheckResponse {
  success: boolean;
  jobId?: string;
  referenceNumber?: string;
  transactionId?: string;
  status?: string;
  estimatedAvailability?: string;
  validationScore?: string;
  message: string;
  timestamp: string;
}

export interface ApiErrorResponse {
  error: string;
  timestamp: string;
}

export interface HealthCheckResponse {
  status: string;
  timestamp: string;
  uptime: number;
  memory: {
    rss: number;
    heapTotal: number;
    heapUsed: number;
    external: number;
  };
  version: string;
}