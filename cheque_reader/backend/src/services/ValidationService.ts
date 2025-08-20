export class ValidationService {
  validateAmount(amount: string): boolean {
    if (!amount) return false;
    const num = parseFloat(amount);
    return !isNaN(num) && num > 0;
  }
} 