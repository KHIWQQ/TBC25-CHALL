export class FraudDetectionService {
  isFraudulent(amount: string, remarks?: string): boolean {
    if (remarks && remarks.toLowerCase().includes('fraud')) {
      return true;
    }
    if (parseFloat(amount) > 100000) {
      return true;
    }
    return false;
  }
} 