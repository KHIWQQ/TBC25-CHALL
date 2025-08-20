export interface CheckImages {
  front: File | null;
  back: File | null;
}

export interface ImagePreviews {
  front: string | null;
  back: string | null;
}

export interface DepositStatus {
  status: 'idle' | 'processing' | 'success' | 'error';
  message: string;
}

export interface DepositResponse {
  success: boolean;
  referenceNumber?: string;
  transactionId?: string;
  status?: string;
  estimatedAvailability?: string;
  validationScore?: string;
  message: string;
  timestamp: string;
}

export interface ApiError {
  error: string;
  timestamp: string;
}

export interface ValidationErrors {
  amount?: string;
  images?: string;
  general?: string;
}