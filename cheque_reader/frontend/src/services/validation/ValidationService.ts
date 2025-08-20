import { ValidationErrors } from '../../types';

export class ValidationService {
  public validateAmount(amount: string): string | null {
    if (!amount || amount.trim() === '') {
      return 'Amount is required';
    }

    const numericAmount = parseFloat(amount);
    
    if (isNaN(numericAmount)) {
      return 'Amount must be a valid number';
    }

    if (numericAmount <= 0) {
      return 'Amount must be greater than 0';
    }

    if (numericAmount > 999999.99) {
      return 'Amount cannot exceed $999,999.99';
    }

    const decimalPlaces = (amount.split('.')[1] || '').length;
    if (decimalPlaces > 2) {
      return 'Amount cannot have more than 2 decimal places';
    }

    return null;
  }

  public validateImages(frontImage: File | null, backImage: File | null): string | null {
    if (!frontImage || !backImage) {
      return 'Both front and back images are required';
    }

    const maxSize = 10 * 1024 * 1024; // 10MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];

    if (frontImage.size > maxSize) {
      return 'Front image size cannot exceed 10MB';
    }

    if (backImage.size > maxSize) {
      return 'Back image size cannot exceed 10MB';
    }

    if (!allowedTypes.includes(frontImage.type)) {
      return 'Front image must be JPEG, JPG, or PNG format';
    }

    if (!allowedTypes.includes(backImage.type)) {
      return 'Back image must be JPEG, JPG, or PNG format';
    }

    return null;
  }

  public validateForm(amount: string, frontImage: File | null, backImage: File | null): ValidationErrors {
    const errors: ValidationErrors = {};

    const amountError = this.validateAmount(amount);
    if (amountError) {
      errors.amount = amountError;
    }

    const imagesError = this.validateImages(frontImage, backImage);
    if (imagesError) {
      errors.images = imagesError;
    }

    return errors;
  }
}
