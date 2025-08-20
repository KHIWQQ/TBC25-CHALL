import { useState, useCallback } from 'react';
import { DepositStatus, DepositResponse } from '../types';
import { CheckDepositApi } from '../services/api/CheckDepositApi';
import { ValidationService } from '../services/validation/ValidationService';

export const useCheckDeposit = () => {
  const [depositStatus, setDepositStatus] = useState<DepositStatus>({
    status: 'idle',
    message: '',
  });

  const checkDepositApi = new CheckDepositApi();
  const validationService = new ValidationService();

  const submitDeposit = useCallback(async (
    amount: string,
    frontImage: File | null,
    backImage: File | null,
    remarks?: string
  ): Promise<DepositResponse | null> => {
    const validationErrors = validationService.validateForm(amount, frontImage, backImage);
    
    if (Object.keys(validationErrors).length > 0) {
      const errorMessage = Object.values(validationErrors)[0];
      setDepositStatus({
        status: 'error',
        message: errorMessage,
      });
      return null;
    }

    setDepositStatus({ status: 'processing', message: 'Processing deposit...' });

    try {
      const response = await checkDepositApi.depositCheck({
        frontImage: frontImage!,
        backImage: backImage!,
        amount,
        remarks,
      });

      if (response.success) {
        setDepositStatus({
          status: 'success',
          message: response.message,
        });
      } else {
        setDepositStatus({
          status: 'error',
          message: response.message,
        });
      }

      return response;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Network error. Please try again.';
      setDepositStatus({
        status: 'error',
        message: errorMessage,
      });
      return null;
    }
  }, [checkDepositApi, validationService]);

  const resetStatus = useCallback(() => {
    setDepositStatus({ status: 'idle', message: '' });
  }, []);

  return {
    depositStatus,
    submitDeposit,
    resetStatus,
  };
};
